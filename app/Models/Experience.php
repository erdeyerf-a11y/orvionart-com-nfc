<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experience extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'scheduled', 'published', 'archived'];

    protected $fillable = [
        'created_by', 'title', 'slug', 'public_id', 'status', 'summary', 'body',
        'scheduled_at', 'published_at', 'ends_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'ends_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($term): void {
            $nested->where('title', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('summary', 'like', "%{$term}%");
        });
    }

    protected static function booted(): void
    {
        static::creating(function (Experience $experience): void {
            if (blank($experience->public_id)) {
                $experience->public_id = Str::random(40);
            }
        });
    }

    /**
     * Scope for the single public-availability rule used by public player queries.
     * Keep this aligned with isPubliclyAvailable() so QR/NFC and direct URLs agree.
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $available): void {
            $available->where(function (Builder $published): void {
                $published->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->where(function (Builder $end): void {
                        $end->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    });
            })->orWhere(function (Builder $scheduled): void {
                $scheduled->where('status', 'scheduled')
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '<=', now())
                    ->where(function (Builder $end): void {
                        $end->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    });
            });
        });
    }

    public function isPubliclyAvailable(): bool
    {
        if ($this->ends_at !== null && $this->ends_at->lte(now())) {
            return false;
        }

        if ($this->status === 'published') {
            return $this->published_at !== null && $this->published_at->lte(now());
        }

        return $this->status === 'scheduled'
            && $this->scheduled_at !== null
            && $this->scheduled_at->lte(now());
    }

    public function publicUrl(): ?string
    {
        if (!$this->isPubliclyAvailable() || blank($this->public_id)) {
            return null;
        }

        return route('experiences.public', ['publicId' => $this->public_id, 'slug' => $this->slug]);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class)->latest();
    }

    public function qrLinks(): HasMany
    {
        return $this->hasMany(QrLink::class)->latest();
    }
}
