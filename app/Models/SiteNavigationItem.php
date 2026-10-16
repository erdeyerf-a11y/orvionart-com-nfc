<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteNavigationItem extends Model
{
    protected $fillable = ['location','label','site_page_id','external_url','sort_order','is_active'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function page(): BelongsTo { return $this->belongsTo(SitePage::class, 'site_page_id'); }
    public function resolvedUrl(): ?string
    {
        if ($this->page) return ($this->page->isPubliclyAvailable() && ($this->location !== 'header' || (bool) $this->page->published_show_in_menu)) ? $this->page->publicUrl() : null;
        $url = trim((string)$this->external_url);
        return preg_match('~^https://[^\s]+$~i', $url) ? $url : null;
    }
}
