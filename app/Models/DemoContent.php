<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoContent extends Model
{
    protected $fillable = ['created_by', 'title', 'summary', 'body', 'sort_order', 'is_active', 'media_disk', 'media_path', 'media_mime_type', 'media_size'];

    protected function casts(): array { return ['is_active' => 'boolean', 'sort_order' => 'integer', 'media_size' => 'integer']; }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function assignments(): HasMany { return $this->hasMany(DemoDailyAssignment::class); }
    public function mediaUrl(): ?string { return $this->media_path ? route('demo.media', ['content' => $this->id]) : null; }
}
