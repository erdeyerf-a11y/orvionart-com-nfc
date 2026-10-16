<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePageRevision extends Model
{
    public $timestamps = false;
    protected $fillable = ['site_page_id','version','event','change_summary','snapshot','actor_id','created_at'];
    protected function casts(): array { return ['snapshot'=>'array','created_at'=>'datetime']; }
    public function page(): BelongsTo { return $this->belongsTo(SitePage::class, 'site_page_id'); }
}
