<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePageRedirect extends Model
{
    protected $fillable = ['site_page_id', 'old_slug', 'new_slug', 'status_code', 'created_by'];

    protected function casts(): array
    {
        return ['status_code' => 'integer'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'site_page_id');
    }
}
