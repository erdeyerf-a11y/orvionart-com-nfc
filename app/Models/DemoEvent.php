<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['demo_session_id', 'event_type', 'demo_content_id', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
}
