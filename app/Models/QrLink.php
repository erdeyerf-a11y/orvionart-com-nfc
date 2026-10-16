<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QrLink extends Model
{
    protected $fillable = ['experience_id', 'created_by', 'label', 'token', 'is_active', 'scan_count', 'last_scanned_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_scanned_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (QrLink $link): void {
            if (blank($link->token)) {
                $link->token = Str::random(40);
            }
        });
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redirectUrl(): string
    {
        return route('qr.redirect', ['token' => $this->token]);
    }

    public function qrImageUrl(): string
    {
        return route('admin.qr-links.image', $this);
    }

    /** Admin-only lifecycle summary; does not change the stable QR/NFC destination. */
    public function targetAvailability(): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }

        $experience = $this->experience;
        if (!$experience) {
            return 'missing';
        }

        if ($experience->ends_at !== null && $experience->ends_at->lte(now())) {
            return 'expired';
        }

        if ($experience->isPubliclyAvailable()) {
            return 'available';
        }

        if ($experience->status === 'scheduled'
            && $experience->scheduled_at !== null
            && $experience->scheduled_at->isFuture()
            && ($experience->ends_at === null || $experience->ends_at->gt($experience->scheduled_at))
            && ($experience->ends_at === null || $experience->ends_at->isFuture())) {
            return 'scheduled';
        }

        return 'unavailable';
    }

    public function targetAvailabilityLabel(): string
    {
        return match ($this->targetAvailability()) {
            'available' => 'Hedef yayında',
            'scheduled' => 'Yayın zamanı bekleniyor',
            'disabled' => 'Bağlantı devre dışı',
            'missing' => 'Deneyim bulunamadı',
            'expired' => 'İçeriğin erişim süresi doldu',
            default => 'Hedef şu anda erişime kapalı',
        };
    }
}
