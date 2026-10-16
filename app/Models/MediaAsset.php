<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    protected $fillable = [
        'experience_id', 'site_page_id', 'uploaded_by', 'title', 'alt_text', 'disk', 'path', 'processed_path', 'thumbnail_path',
        'original_name', 'mime_type', 'kind', 'file_size', 'processing_status', 'processing_error',
        'processing_attempts', 'processed_at', 'duration_seconds', 'video_width', 'video_height', 'video_codec', 'audio_codec',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'duration_seconds' => 'float',
            'processing_attempts' => 'integer',
            'video_width' => 'integer',
            'video_height' => 'integer',
        ];
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function sitePage(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'site_page_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function publicUrl(): string
    {
        return route('media.public', ['asset' => $this->id]);
    }

    /**
     * Whether the asset is referenced by the current draft, published snapshot,
     * or any retained Site Builder revision. Historical snapshots must remain
     * restorable, so a media file cannot be purged while a revision still points
     * at it even when the current page no longer displays the file.
     */
    public function isReferencedBySitePage(): bool
    {
        if ($this->site_page_id === null) {
            return false;
        }

        $page = SitePage::query()->find($this->site_page_id);
        if (!$page) {
            return false;
        }

        if ($this->snapshotReferencesThisAsset([
            'sections' => $page->sections,
            'published_sections' => $page->published_sections,
            'social_image_asset_id' => $page->social_image_asset_id,
            'published_social_image_asset_id' => $page->published_social_image_asset_id,
        ])) {
            return true;
        }

        $referenced = false;
        SitePageRevision::query()
            ->where('site_page_id', $page->id)
            ->select(['id', 'snapshot'])
            ->orderBy('id')
            ->chunkById(100, function ($revisions) use (&$referenced): bool {
                foreach ($revisions as $revision) {
                    if (is_array($revision->snapshot) && $this->snapshotReferencesThisAsset($revision->snapshot)) {
                        $referenced = true;
                        return false;
                    }
                }

                return true;
            });

        return $referenced;
    }

    /** Check all media-bearing fields supported by Site Builder snapshots. */
    private function snapshotReferencesThisAsset(array $snapshot): bool
    {
        $assetId = (int) $this->id;
        if ($assetId <= 0) {
            return false;
        }

        if ((int) ($snapshot['social_image_asset_id'] ?? 0) === $assetId
            || (int) ($snapshot['published_social_image_asset_id'] ?? 0) === $assetId) {
            return true;
        }

        foreach (['sections', 'published_sections'] as $field) {
            foreach (($snapshot[$field] ?? []) as $section) {
                if (!is_array($section)) {
                    continue;
                }
                if ((int) ($section['image_asset_id'] ?? 0) === $assetId) {
                    return true;
                }
                foreach (($section['items'] ?? []) as $item) {
                    if (is_array($item) && (int) ($item['image_asset_id'] ?? 0) === $assetId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Whether a Demo Engine content item references the exact same stored file.
     * Demo content currently stores a disk/path pair rather than a MediaAsset ID,
     * so this check must compare both values before the bytes can be deleted.
     */
    public function isReferencedByDemoContent(): bool
    {
        return DemoContent::query()
            ->where('media_disk', $this->disk)
            ->where('media_path', $this->path)
            ->exists();
    }

    /**
     * Whether another library row points to the exact same stored bytes.
     * Deleting one row must not remove a file still represented by another row.
     */
    public function isReferencedByAnotherAsset(): bool
    {
        return self::query()
            ->where('disk', $this->disk)
            ->where('path', $this->path)
            ->whereKeyNot($this->getKey())
            ->exists();
    }

    /** An asset may be deleted only after all known content references are removed. */
    public function canBeDeletedSafely(): bool
    {
        return $this->experience_id === null
            && !$this->isReferencedBySitePage()
            && !$this->isReferencedByDemoContent()
            && !$this->isReferencedByAnotherAsset();
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }
}
