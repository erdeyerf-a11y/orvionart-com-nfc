<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoSetting extends Model
{
    protected $fillable = ['is_active', 'journey_days', 'retention_days', 'title', 'paused_message'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'journey_days' => 'integer', 'retention_days' => 'integer'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['setting_key' => 'default'], [
            'is_active' => false,
            'journey_days' => 7,
            'retention_days' => 30,
            'title' => 'ORVIONART Demo',
            'paused_message' => 'Demo şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.',
        ]);
    }
}
