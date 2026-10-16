<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoSession extends Model
{
    protected $fillable = ['device_token_hash', 'started_at', 'ends_at', 'last_seen_at', 'expired_at'];
    protected function casts(): array { return ['started_at' => 'datetime', 'ends_at' => 'datetime', 'last_seen_at' => 'datetime', 'expired_at' => 'datetime']; }
    public function assignments(): HasMany { return $this->hasMany(DemoDailyAssignment::class); }
    public function isExpired(): bool { return $this->ends_at->lte(now()) || $this->expired_at !== null; }
}
