<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['setting_key','setting_value'];
    protected function casts(): array { return ['setting_value'=>'array']; }
    public static function getValue(string $key, array $default = []): array
    {
        $row = static::query()->where('setting_key', $key)->first();
        return is_array($row?->setting_value) ? $row->setting_value : $default;
    }
}
