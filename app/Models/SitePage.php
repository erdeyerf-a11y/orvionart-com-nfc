<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SitePage extends Model
{
    public const STATUSES = ['draft', 'published', 'archived'];
    public const TYPES = ['home', 'standard', 'qr-solutions', 'nfc-solutions', 'about', 'contact', 'faq', 'legal'];
    public const SECTION_TYPES = ['hero', 'hero_slider', 'text', 'image_text', 'cards', 'features', 'steps', 'faq', 'cta', 'contact', 'experience_list', 'qr_nfc_promo', 'demo_promo', 'free_plan_promo', 'testimonials', 'stats'];

    protected $fillable = ['title','slug','page_type','status','show_in_menu','sections','published_sections','seo_title','meta_description','canonical_url','noindex','published_seo_title','published_meta_description','published_canonical_url','published_noindex','published_title','published_slug','published_page_type','published_show_in_menu','social_image_asset_id','published_social_image_asset_id','version','created_by','published_by','published_at'];

    protected function casts(): array
    {
        return ['sections'=>'array','published_sections'=>'array','show_in_menu'=>'boolean','noindex'=>'boolean','published_noindex'=>'boolean','published_show_in_menu'=>'boolean','published_at'=>'datetime'];
    }

    public function revisions(): HasMany { return $this->hasMany(SitePageRevision::class)->latest('version'); }
    public function mediaAssets(): HasMany { return $this->hasMany(MediaAsset::class)->latest(); }

    public function isPubliclyAvailable(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now()) && is_array($this->published_sections) && !empty($this->published_slug);
    }

    public function publicUrl(): string
    {
        $slug = $this->isPubliclyAvailable() ? (string) $this->published_slug : (string) $this->slug;
        return $slug === 'home' ? route('site.home') : route('site.page', ['slug' => $slug]);
    }

    public function snapshot(): array
    {
        return [
            'title'=>$this->title,'slug'=>$this->slug,'page_type'=>$this->page_type,'status'=>$this->status,
            'show_in_menu'=>$this->show_in_menu,'sections'=>$this->sections,'published_sections'=>$this->published_sections,
            'seo_title'=>$this->seo_title,'meta_description'=>$this->meta_description,'canonical_url'=>$this->canonical_url,
            'noindex'=>$this->noindex,'published_seo_title'=>$this->published_seo_title,'published_meta_description'=>$this->published_meta_description,
            'published_canonical_url'=>$this->published_canonical_url,'published_noindex'=>$this->published_noindex,
            'published_title'=>$this->published_title,'published_slug'=>$this->published_slug,'published_page_type'=>$this->published_page_type,
            'published_show_in_menu'=>$this->published_show_in_menu,'social_image_asset_id'=>$this->social_image_asset_id,
            'published_social_image_asset_id'=>$this->published_social_image_asset_id,'version'=>$this->version,'published_by'=>$this->published_by,
            'published_at'=>$this->published_at?->toISOString(),
        ];
    }
}
