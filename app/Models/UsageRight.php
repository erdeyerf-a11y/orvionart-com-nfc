<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRight extends Model
{
    protected $fillable = [
        'right_type', 'label', 'subject_type', 'subject_reference', 'grant_source',
        'starts_at', 'ends_at', 'status', 'notes', 'created_by',
        'revoked_by', 'revoked_at', 'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** Display-only status. This does not grant or enforce access to any resource. */
    public function displayStatus(): string
    {
        if ($this->status === 'revoked') return 'İptal edildi';
        if ($this->starts_at && $this->starts_at->isFuture()) return 'Henüz başlamadı';
        if ($this->ends_at && $this->ends_at->isPast()) return 'Süresi doldu';
        return 'Kayıt aktif';
    }

    public function isRevocable(): bool
    {
        return $this->status === 'active';
    }
}
