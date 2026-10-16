<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoDailyAssignment extends Model
{
    protected $fillable = ['demo_session_id', 'demo_content_id', 'assigned_for'];
    protected function casts(): array { return ['assigned_for' => 'date']; }
    public function session(): BelongsTo { return $this->belongsTo(DemoSession::class, 'demo_session_id'); }
    public function content(): BelongsTo { return $this->belongsTo(DemoContent::class, 'demo_content_id'); }
}
