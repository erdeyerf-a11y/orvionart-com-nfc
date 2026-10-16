<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoUploadSession extends Model
{
    protected $fillable = [
        'uuid', 'uploaded_by', 'experience_id', 'media_asset_id', 'title', 'alt_text',
        'original_name', 'declared_mime', 'total_size', 'chunk_size', 'total_chunks',
        'received_chunks', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'received_chunks' => 'array',
            'chunk_hashes' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function chunkDirectory(): string
    {
        return 'orvionart-video-uploads/'.$this->uuid.'/chunks';
    }

    public function hasReceivedAllChunks(): bool
    {
        $received = array_unique(array_map('intval', $this->received_chunks ?? []));
        sort($received);
        return $received === range(0, $this->total_chunks - 1);
    }
}
