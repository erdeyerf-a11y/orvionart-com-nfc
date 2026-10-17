<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\SiteNavigationItem;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\Experience;
use App\Models\DemoSetting;
use App\Models\DemoContent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

class SitePageController extends Controller
{
    public function home(Request $request): Response
    {
        $page=SitePage::where('published_slug','home')->first();
        if (!$page || !$page->isPubliclyAvailable()) abort(404);
        return $this->render($request,$page);
    }

    public function show(Request $request,string $slug): Response
    {
        abort_if(in_array($slug,['admin','api','go','media','demo','health','robots','p','login','logout'],true),404);
        $page=SitePage::where('published_slug',$slug)->first();
        if ($page && $page->isPubliclyAvailable()) return $this->render($request,$page);
        if (SitePage::where('slug', $slug)->exists()) abort(404); // A draft must not reveal a page or redirect alias.
        $redirect = \App\Models\SitePageRedirect::where('old_slug', $slug)->first();
        if ($redirect) {
            $target = SitePage::where('published_slug', $redirect->new_slug)->first();
            if ($target && $target->isPubliclyAvailable()) return redirect()->to($target->publicUrl(), (int) $redirect->status_code);
        }
        abort(404);
    }

    public function sitemap(): Response
    {
        $urls = [];
        if (config('app.env') === 'production' && (bool) config('orvionart.public_indexing_enabled', false)) {
            $pages = SitePage::query()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->where('published_noindex', false)
                ->get();

            foreach ($pages as $item) {
                $loc = $item->published_canonical_url ?: $this->configuredPublicUrl('/s/'.$item->published_slug);
                if (!$this->isAllowedSitemapUrl($loc)) {
                    continue;
                }
                $this->addSitemapUrl($urls, $loc, ($item->published_at ?: $item->created_at ?: now())->toAtomString());
            }

            $experiences = Experience::query()->where(function ($q) {
                $q->where(fn ($p) => $p->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now()))
                    ->orWhere(fn ($s) => $s->where('status', 'scheduled')->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now()));
            })->get();

            foreach ($experiences as $item) {
                $loc = $this->configuredPublicUrl('/p/'.$item->public_id.'/'.$item->slug);
                if (!$this->isAllowedSitemapUrl($loc)) {
                    continue;
                }
                // Scheduled records must use the time they became public, not a stale publication timestamp.
                $publicChangedAt = $item->status === 'scheduled'
                    ? ($item->scheduled_at ?: $item->created_at ?: now())
                    : ($item->published_at ?: $item->created_at ?: now());
                $this->addSitemapUrl($urls, $loc, $publicChangedAt->toAtomString());
            }
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";
        foreach ($urls as $url) {
            $loc = htmlspecialchars($url['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $lastmod = htmlspecialchars($url['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $xml .= '<url><loc>'.$loc.'</loc><lastmod>'.$lastmod.'</lastmod></url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=300']);
    }

    private function configuredPublicUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    /** Only absolute URLs on the configured application origin belong in this sitemap. */
    private function isAllowedSitemapUrl(?string $url): bool
    {
        if (!is_string($url) || trim($url) === '') {
            return false;
        }

        $candidate = parse_url($url);
        $origin = parse_url((string) config('app.url'));
        if (!is_array($candidate) || !is_array($origin)) {
            return false;
        }
        if (empty($candidate['scheme']) || empty($candidate['host']) || empty($origin['scheme']) || empty($origin['host'])) {
            return false;
        }
        if (isset($candidate['user']) || isset($candidate['pass']) || isset($candidate['fragment'])) {
            return false;
        }

        $candidateScheme = strtolower((string) $candidate['scheme']);
        $originScheme = strtolower((string) $origin['scheme']);
        if (!in_array($candidateScheme, ['http', 'https'], true) || $candidateScheme !== $originScheme) {
            return false;
        }
        if (strtolower((string) $candidate['host']) !== strtolower((string) $origin['host'])) {
            return false;
        }

        $defaultPort = $candidateScheme === 'https' ? 443 : 80;
        return (int) ($candidate['port'] ?? $defaultPort) === (int) ($origin['port'] ?? $defaultPort);
    }

    /** De-duplicate canonical URLs; retain the newest public-version timestamp. */
    private function addSitemapUrl(array &$urls, string $loc, string $lastmod): void
    {
        if (!isset($urls[$loc]) || strtotime($lastmod) > strtotime($urls[$loc]['lastmod'])) {
            $urls[$loc] = ['loc' => $loc, 'lastmod' => $lastmod];
        }
    }

    private function render(Request $request,SitePage $page): Response
    {
        $settings=SiteSetting::getValue('general',['site_name'=>'ORVIONART','brand_text'=>'ORVIONART','primary_color'=>'#10202c','accent_color'=>'#58d3cb','font_family'=>'system','content_width'=>1160,'default_seo_title'=>'ORVIONART','default_meta_description'=>'','maintenance_mode'=>false,'maintenance_message'=>'Sitemiz kısa süreli bakımda.','footer_text'=>'© ORVIONART']);
        abort_if(($settings['maintenance_mode']??false) && !$request->user(),503,$settings['maintenance_message']??'Sitemiz bakımda.');
        $navigation=SiteNavigationItem::with('page')->where('is_active',true)->orderBy('sort_order')->orderBy('id')->get()->filter(fn($item)=>$item->resolvedUrl()!==null)->groupBy('location');
        $sections=$page->published_sections ?? [];
        $socialAssetId = $page->published_social_image_asset_id;
        $socialAsset = $socialAssetId ? \App\Models\MediaAsset::where('id', $socialAssetId)->where('site_page_id', $page->id)->where('kind', 'image')->first() : null;
        $socialImageUrl = $socialAsset?->publicUrl();
        if (!$socialImageUrl) {
            $globalSocialPath = $settings['social_image_path'] ?? null;
            if (is_string($globalSocialPath)
                && preg_match('~^orvionart-brand/social_image_path-[A-Za-z0-9]{40}\.(?:png|jpg|webp)$~', $globalSocialPath) === 1
                && \Illuminate\Support\Facades\Storage::disk('local')->exists($globalSocialPath)) {
                $socialImageUrl = route('brand.asset', 'social-image');
            }
        }
        $needsExperienceList=collect($sections)->contains(fn($section)=>($section['type']??'')==='experience_list' && ($section['is_active']??true));
        $publicExperiences=$needsExperienceList ? Experience::query()->where(function($q){
            $q->where(fn($p)=>$p->where('status','published')->whereNotNull('published_at')->where('published_at','<=',now()))
              ->orWhere(fn($s)=>$s->where('status','scheduled')->whereNotNull('scheduled_at')->where('scheduled_at','<=',now()));
        })->orderBy('title')->limit(24)->get() : collect();
        $hasDemoPromo=collect($sections)->contains(fn($section)=>($section['type']??'')==='demo_promo' && ($section['is_active']??true));
        $demoActive=$hasDemoPromo && DemoSetting::current()->is_active && DemoContent::query()->where('is_active',true)->exists();
        return response()->view('public.site-page',array_merge(compact('page','settings','navigation','sections','publicExperiences','demoActive','socialImageUrl'),['preview'=>false,'viewport'=>'desktop']),200,[
            // A staging/non-production hostname must never become indexable just because
            // a published snapshot has its own SEO indexing flag disabled.
            'X-Robots-Tag'=>config('app.env') !== 'production'
                ? ($page->published_noindex ? 'noindex, nofollow' : 'noindex, nofollow, noarchive')
                : ($page->published_noindex ? 'noindex, nofollow' : 'index, follow'),
            // Publication state can change immediately (unpublish/archive). Do not let a
            // browser or shared cache keep serving a page after its public lifecycle ends.
            'Cache-Control'=>'no-store, private',
        ]);
    }
}
