<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\SiteNavigationItem;
use App\Models\SitePage;
use App\Models\SitePageRevision;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SiteBuilderController extends Controller
{
    public function index(Request $request): View
    {
        $pages = SitePage::query()->when($request->filled('q'), function ($q) use ($request) {
            $term = trim((string)$request->query('q'));
            $q->where(fn($sub) => $sub->where('title','like',"%{$term}%")->orWhere('slug','like',"%{$term}%"));
        })->when(in_array($request->query('status'), SitePage::STATUSES, true), fn($q) => $q->where('status',$request->query('status')))
            ->withCount('revisions')->latest('updated_at')->paginate(15)->withQueryString();
        return view('admin.site-builder.index', ['pages'=>$pages,'counts'=>[
            'all'=>SitePage::count(),'draft'=>SitePage::where('status','draft')->count(),'published'=>SitePage::where('status','published')->count(),
        ]]);
    }

    public function create(): View { return $this->form(new SitePage(['status'=>'draft','page_type'=>'standard','sections'=>[],'noindex'=>true]), 'Yeni sayfa oluştur', route('admin.site-builder.store'), 'POST'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft'; $data['published_sections'] = null; $data['published_at'] = null; $data['published_by'] = null; $data['version'] = 1;
        $page = DB::transaction(function () use ($request,$data) {
            $page = SitePage::create($data);
            $this->revision($request,$page,'created','İlk taslak oluşturuldu.');
            $this->audit($request,'site_page.created',$page,['slug'=>$page->slug]);
            return $page;
        });
        return redirect()->route('admin.site-builder.edit',$page)->with('success','Taslak sayfa oluşturuldu. Ziyaretçilere yayınlanmaz.');
    }

    public function edit(SitePage $sitePage): View { return $this->form($sitePage, 'Sayfayı düzenle', route('admin.site-builder.update',$sitePage), 'PUT'); }

    public function update(Request $request, SitePage $sitePage): RedirectResponse
    {
        $data = $this->validated($request, $sitePage);

        DB::transaction(function () use ($request, $sitePage, $data): void {
            // Media deletion locks asset rows first. Page transitions must use the
            // same lock order, then revalidate request media IDs after acquiring locks.
            $this->lockPageMediaAssets((int) $sitePage->getKey());
            // Never write from the route-bound snapshot: another administrator may
            // have saved or published this page since route model binding occurred.
            $lockedPage = SitePage::query()->whereKey($sitePage->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(is_array($data['sections'] ?? null), 422, 'Sayfa bölümleri geçerli değil.');
            $this->validateSectionMedia($lockedPage, $data['sections']);
            $this->validateSocialImage($lockedPage, $data['social_image_asset_id'] ?? null, 'Sosyal paylaşım görseli bu sayfanın medya alanında bulunamadı.');
            $before = $lockedPage->snapshot();
            $lockedPage->fill($data);
            $lockedPage->version = (int) $lockedPage->version + 1;
            $lockedPage->save();
            $this->revision($request, $lockedPage, 'saved', 'Taslak düzenlendi. Önceki taslak sürümü saklandı.');
            $this->audit($request, 'site_page.updated', $lockedPage, [
                'before_version' => $before['version'],
                'version' => $lockedPage->version,
                'slug_changed' => $before['slug'] !== $lockedPage->slug,
            ]);
        });

        return back()->with('success', 'Taslak kaydedildi. Yayındaki sürüm, ayrıca yayınlayana kadar değişmez.');
    }

    public function publish(Request $request, SitePage $sitePage): RedirectResponse
    {
        DB::transaction(function () use ($request, $sitePage): void {
            // Lock page-owned media first to coordinate with media deletion, then lock
            // the page and validate the latest committed draft while those locks hold.
            $this->lockPageMediaAssets((int) $sitePage->getKey());
            // Publication always snapshots the latest committed draft under a row lock.
            $lockedPage = SitePage::query()->whereKey($sitePage->getKey())->lockForUpdate()->firstOrFail();
            $publishedSlugOwner = SitePage::query()
                ->where('id', '!=', $lockedPage->getKey())
                ->whereRaw('LOWER(published_slug) = ?', [mb_strtolower((string) $lockedPage->slug)])
                ->exists();
            abort_if($publishedSlugOwner, 422, 'Bu adres başka bir sayfanın yayındaki adresi olarak kullanılıyor. Önce diğer sayfanın adresini değiştirip yayınlayın.');

            abort_unless(is_array($lockedPage->sections), 422, 'Sayfa bölümleri geçerli değil.');
            $this->validateSectionMedia($lockedPage, $lockedPage->sections);
            $this->validateSocialImage($lockedPage, $lockedPage->social_image_asset_id, 'Sosyal paylaşım görseli bu sayfanın medya alanında bulunamadı.');

            $oldPublishedSlug = (string) ($lockedPage->published_slug ?? '');
            $lockedPage->published_sections = $lockedPage->sections;
            $lockedPage->published_seo_title = $lockedPage->seo_title;
            $lockedPage->published_meta_description = $lockedPage->meta_description;
            $lockedPage->published_canonical_url = $lockedPage->canonical_url;
            $lockedPage->published_noindex = (bool) $lockedPage->noindex;
            $lockedPage->published_title = $lockedPage->title;
            $lockedPage->published_slug = $lockedPage->slug;
            $lockedPage->published_page_type = $lockedPage->page_type;
            $lockedPage->published_show_in_menu = (bool) $lockedPage->show_in_menu;
            $lockedPage->published_social_image_asset_id = $lockedPage->social_image_asset_id;
            $newPublishedSlug = (string) $lockedPage->published_slug;

            if ($oldPublishedSlug !== '' && $oldPublishedSlug !== $newPublishedSlug) {
                // Keep old public addresses working and collapse any previous redirect chains.
                DB::table('site_page_redirects')->where('site_page_id', $lockedPage->id)->where('old_slug', '!=', $newPublishedSlug)->update(['new_slug' => $newPublishedSlug, 'updated_at' => now()]);
                DB::table('site_page_redirects')->where('old_slug', $newPublishedSlug)->delete();
                DB::table('site_page_redirects')->updateOrInsert(
                    ['old_slug' => $oldPublishedSlug],
                    ['site_page_id' => $lockedPage->id, 'new_slug' => $newPublishedSlug, 'status_code' => 301, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]
                );
            }
            DB::table('site_page_redirects')->where('old_slug', $newPublishedSlug)->delete();
            $lockedPage->status = 'published';
            $lockedPage->published_at = now();
            $lockedPage->published_by = $request->user()->id;
            $lockedPage->noindex = $lockedPage->published_noindex;
            $lockedPage->version = (int) $lockedPage->version + 1;
            $lockedPage->save();
            $this->revision($request, $lockedPage, 'published', 'Yayınlanan içerik ve SEO ayarları kaydedildi.');
            $this->audit($request, 'site_page.published', $lockedPage, ['version' => $lockedPage->version, 'noindex' => $lockedPage->noindex]);
        });

        return back()->with('success', 'Sayfa yayınlandı. Arama motoru indekslemesi varsayılan olarak kapalıdır.');
    }

    public function unpublish(Request $request, SitePage $sitePage): RedirectResponse
    {
        DB::transaction(function () use ($request, $sitePage): void {
            $lockedPage = SitePage::query()->whereKey($sitePage->getKey())->lockForUpdate()->firstOrFail();
            $lockedPage->status = 'draft';
            $lockedPage->version = (int) $lockedPage->version + 1;
            $lockedPage->save();
            $this->revision($request, $lockedPage, 'unpublished', 'Sayfa yayından kaldırıldı.');
            $this->audit($request, 'site_page.unpublished', $lockedPage, ['version' => $lockedPage->version]);
        });

        return back()->with('success', 'Sayfa yayından kaldırıldı; önceki yayın içeriği korunarak tekrar yayınlanabilir.');
    }

    public function preview(Request $request, SitePage $sitePage): Response
    {
        $viewport=in_array($request->query('viewport'),['mobile','tablet','desktop'],true)?$request->query('viewport'):'desktop';
        $sections=$sitePage->sections ?? [];
        $needsExperienceList=collect($sections)->contains(fn($section)=>($section['type']??'')==='experience_list' && ($section['is_active']??true));
        $publicExperiences=$needsExperienceList ? \App\Models\Experience::query()->where(function($q){
            $q->where(fn($p)=>$p->where('status','published')->whereNotNull('published_at')->where('published_at','<=',now()))
              ->orWhere(fn($s)=>$s->where('status','scheduled')->whereNotNull('scheduled_at')->where('scheduled_at','<=',now()));
        })->orderBy('title')->limit(24)->get() : collect();
        $hasDemoPromo=collect($sections)->contains(fn($section)=>($section['type']??'')==='demo_promo' && ($section['is_active']??true));
        $demoActive=$hasDemoPromo && \App\Models\DemoSetting::current()->is_active && \App\Models\DemoContent::query()->where('is_active',true)->exists();
        $socialAsset = $sitePage->social_image_asset_id ? MediaAsset::where('id', $sitePage->social_image_asset_id)->where('site_page_id', $sitePage->id)->where('kind', 'image')->first() : null;
        return response()->view('public.site-page', ['page'=>$sitePage,'sections'=>$sections,'settings'=>SiteSetting::getValue('general', $this->defaultSettings()),'navigation'=>$this->publicNavigation(),'preview'=>true,'viewport'=>$viewport,'publicExperiences'=>$publicExperiences,'demoActive'=>$demoActive,'socialImageUrl'=>$socialAsset?->publicUrl()], 200, [
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function revisions(SitePage $sitePage): View { return view('admin.site-builder.revisions',['page'=>$sitePage,'revisions'=>$sitePage->revisions()->paginate(20)]); }

    public function restore(Request $request, SitePage $sitePage, SitePageRevision $revision): RedirectResponse
    {
        abort_unless((int) $revision->site_page_id === (int) $sitePage->id, 404);
        $snapshot = $revision->snapshot;
        abort_unless(is_array($snapshot), 422, 'Geri alınacak sürümün kayıt biçimi geçersiz.');

        DB::transaction(function () use ($request, $sitePage, $revision, $snapshot): void {
            // Use the same asset-before-page lock order as deletion and publication.
            // A removed media row must never slip between restore validation and save.
            $this->lockPageMediaAssets((int) $sitePage->getKey());
            // Restore must validate and update against the current page state, not
            // the potentially stale model loaded before this transaction began.
            $lockedPage = SitePage::query()->whereKey($sitePage->getKey())->lockForUpdate()->firstOrFail();
            $this->validateRestorableSnapshot($lockedPage, $snapshot);
            $sections = $snapshot['sections'];
            $this->validateSectionMedia($lockedPage, $sections);
            $this->validateSocialImage($lockedPage, $snapshot['social_image_asset_id'] ?? null, 'Geri alınacak sürümün sosyal paylaşım görseli bulunamadı.');

            $lockedPage->title = $snapshot['title'];
            $lockedPage->slug = $snapshot['slug'];
            $lockedPage->page_type = $snapshot['page_type'];
            $lockedPage->show_in_menu = (bool) $snapshot['show_in_menu'];
            $lockedPage->sections = $sections;
            $lockedPage->seo_title = $snapshot['seo_title'] ?? null;
            $lockedPage->meta_description = $snapshot['meta_description'] ?? null;
            $lockedPage->canonical_url = $snapshot['canonical_url'] ?? null;
            $lockedPage->noindex = (bool) ($snapshot['noindex'] ?? true);
            $lockedPage->social_image_asset_id = $snapshot['social_image_asset_id'] ?? null;
            $lockedPage->version = (int) $lockedPage->version + 1;
            $lockedPage->save();
            // A rollback changes the draft only; the current live release stays untouched until explicitly published.
            $this->revision($request, $lockedPage, 'restored', 'Taslak, '.$revision->version.'. sürümden geri alındı. Yayına almak için ayrıca yayınlayın.');
            $this->audit($request, 'site_page.revision_restored', $lockedPage, ['source_revision' => $revision->version, 'new_version' => $lockedPage->version]);
        });

        return redirect()->route('admin.site-builder.edit', $sitePage)->with('success', 'Önceki sürüm taslağa geri alındı. Canlı site değişmedi; isterseniz kontrol edip ayrıca yayınlayın.');
    }

    /**
     * Historical snapshots are data, not trusted instructions. Validate them before
     * applying a restore so stale/corrupt snapshots cannot violate current page rules.
     */
    private function validateRestorableSnapshot(SitePage $page, array $snapshot): void
    {
        $title = $snapshot['title'] ?? null;
        $slug = $snapshot['slug'] ?? null;
        $pageType = $snapshot['page_type'] ?? null;
        $sections = $snapshot['sections'] ?? null;

        $errors = [];
        if (!is_string($title) || trim($title) === '' || mb_strlen($title) > 180) {
            $errors['revision'] = 'Geri alınacak sürümde geçerli bir sayfa başlığı bulunamadı.';
        }
        if (!is_string($slug) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $errors['revision'] = 'Geri alınacak sürümde geçerli bir sayfa adresi bulunamadı.';
        }
        if (!is_string($pageType) || !in_array($pageType, SitePage::TYPES, true)) {
            $errors['revision'] = 'Geri alınacak sürümde geçerli bir sayfa türü bulunamadı.';
        }
        if (!is_array($sections) || count($sections) > 40) {
            $errors['revision'] = 'Geri alınacak sürümün bölüm verisi geçersiz.';
        }

        // Validate every value that is later rendered as a URL or consumed by Blade.
        // A historical snapshot may be stale or corrupted and must not bypass current form rules.
        if (!array_key_exists('show_in_menu', $snapshot) || !$this->isSnapshotBoolean($snapshot['show_in_menu'])) {
            $errors['revision'] = 'Geri alınacak sürümün menü ayarı geçersiz.';
        }
        if (array_key_exists('noindex', $snapshot) && !$this->isSnapshotBoolean($snapshot['noindex'])) {
            $errors['revision'] = 'Geri alınacak sürümün indeksleme ayarı geçersiz.';
        }
        foreach ([['seo_title', 180], ['meta_description', 320]] as [$field, $maxLength]) {
            $value = $snapshot[$field] ?? null;
            if ($value !== null && (!is_string($value) || mb_strlen($value) > $maxLength)) {
                $errors['revision'] = 'Geri alınacak sürümün SEO alanları geçersiz.';
            }
        }
        $canonical = $snapshot['canonical_url'] ?? null;
        if ($canonical !== null && $canonical !== '' && !$this->isSafeSnapshotCanonical($canonical)) {
            $errors['revision'] = 'Geri alınacak sürümün canonical adresi geçersiz; yalnızca geçerli HTTPS adresi kullanılabilir.';
        }

        if (is_array($sections)) {
            foreach ($sections as $section) {
                if (!is_array($section) || !is_string($section['type'] ?? null) || !in_array($section['type'], SitePage::SECTION_TYPES, true)) {
                    $errors['revision'] = 'Geri alınacak sürümde geçersiz bir bölüm türü var.';
                    break;
                }
                foreach (['title' => 180, 'subtitle' => 240, 'body' => 12000, 'image_alt' => 250, 'cta_label' => 80] as $field => $maxLength) {
                    $value = $section[$field] ?? '';
                    if (!is_string($value) || mb_strlen($value) > $maxLength) {
                        $errors['revision'] = 'Geri alınacak sürümde geçersiz bölüm metni var.';
                        break 2;
                    }
                }
                if (!in_array($section['variant'] ?? 'default', ['default', 'soft', 'dark', 'accent'], true)) {
                    $errors['revision'] = 'Geri alınacak sürümde geçersiz bölüm görünümü var.';
                    break;
                }
                if (!$this->isSnapshotAssetId($section['image_asset_id'] ?? null)) {
                    $errors['revision'] = 'Geri alınacak sürümde geçersiz görsel kimliği var.';
                    break;
                }
                if (array_key_exists('is_active', $section) && !$this->isSnapshotBoolean($section['is_active'])) {
                    $errors['revision'] = 'Geri alınacak sürümde geçersiz bölüm durumu var.';
                    break;
                }
                if (!$this->isSafeSnapshotLink($section['cta_url'] ?? '')) {
                    $errors['revision'] = 'Geri alınacak sürümde güvenli olmayan bir buton adresi var.';
                    break;
                }
                $items = $section['items'] ?? [];
                if (!is_array($items) || count($items) > 100) {
                    $errors['revision'] = 'Geri alınacak sürümdeki bölüm öğeleri geçersiz.';
                    break;
                }
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        $errors['revision'] = 'Geri alınacak sürümde geçersiz bir bölüm öğesi var.';
                        break 2;
                    }
                    foreach (['title' => 180, 'text' => 12000, 'cta_label' => 80] as $field => $maxLength) {
                        $value = $item[$field] ?? '';
                        if (!is_string($value) || mb_strlen($value) > $maxLength) {
                            $errors['revision'] = 'Geri alınacak sürümde geçersiz bölüm öğesi metni var.';
                            break 3;
                        }
                    }
                    if (!$this->isSafeSnapshotLink($item['cta_url'] ?? '')) {
                        $errors['revision'] = 'Geri alınacak sürümde slider için güvenli olmayan bir bağlantı var.';
                        break 2;
                    }
                    if (!$this->isSnapshotAssetId($item['image_asset_id'] ?? null)) {
                        $errors['revision'] = 'Geri alınacak sürümde slider görsel kimliği geçersiz.';
                        break 2;
                    }
                }
            }
        }

        if ($errors === [] && (($pageType === 'home' && $slug !== 'home') || ($slug === 'home' && $pageType !== 'home'))) {
            $errors['revision'] = 'Geri alınacak sürümün ana sayfa türü ile adresi birbiriyle uyuşmuyor.';
        }
        if ($errors === [] && (in_array($slug, ['admin', 'api', 'go', 'media', 'demo', 'health', 'robots', 'p', 'login', 'logout', 'sitemap'], true) || str_starts_with($slug, 'admin-'))) {
            $errors['revision'] = 'Geri alınacak sürüm sistem için ayrılmış bir adres kullanıyor.';
        }
        if ($errors === [] && SitePage::query()->where('id', '!=', $page->id)->where('slug', $slug)->exists()) {
            $errors['revision'] = 'Bu eski adres artık başka bir sayfada kullanılıyor. Geri alma işlemi yapılmadı.';
        }
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    private function isSnapshotBoolean(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1'], true);
    }

    private function isSnapshotAssetId(mixed $value): bool
    {
        return $value === null || $value === '' || (is_int($value) && $value > 0) || (is_string($value) && ctype_digit($value) && (int) $value > 0);
    }

    private function isSafeSnapshotLink(mixed $url): bool
    {
        if (!is_string($url)) {
            return false;
        }
        $url = trim($url);
        if ($url === '') {
            return true;
        }
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return false;
        }
        if (str_starts_with($url, '#')) {
            return true;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        if (preg_match('~^https://~i', $url) !== 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private function isSafeSnapshotCanonical(mixed $url): bool
    {
        if (!is_string($url) || mb_strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['fragment']);
    }

    public function settings(): View { return view('admin.site-builder.settings',['settings'=>SiteSetting::getValue('general',$this->defaultSettings())]); }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'site_name'=>['required','string','max:100'],'brand_text'=>['required','string','max:100'],
            'primary_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],'accent_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_family'=>['required',Rule::in(['system','serif','sans-serif'])],
            // New presentation controls are optional at the request boundary so older
            // clients/forms remain compatible; defaults/current values are merged below.
            'heading_font_family'=>['sometimes','required',Rule::in(['system','serif','sans-serif'])],
            'body_font_family'=>['sometimes','required',Rule::in(['system','serif','sans-serif'])],
            'background_color'=>['sometimes','required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'surface_color'=>['sometimes','required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_style'=>['sometimes','required',Rule::in(['pill','rounded','square'])],
            'header_behavior'=>['sometimes','required',Rule::in(['normal','sticky'])],
            'footer_layout'=>['sometimes','required',Rule::in(['split','stacked'])],
            'content_width'=>['required','integer','min:760','max:1440'],'default_seo_title'=>['nullable','string','max:180'],
            'default_meta_description'=>['nullable','string','max:320'],'contact_email'=>['nullable','email','max:180'],
            'contact_phone'=>['nullable','string','max:40'],'maintenance_mode'=>['nullable','boolean'],
            'maintenance_message'=>['nullable','string','max:500'],'footer_text'=>['nullable','string','max:250'],
            'logo_file'=>['nullable','file','mimetypes:image/jpeg,image/png,image/webp','max:4096'],
            'favicon_file'=>['nullable','file','mimetypes:image/jpeg,image/png,image/webp','max:2048'],
            'social_image_file'=>['nullable','file','mimetypes:image/jpeg,image/png,image/webp','max:5120'],
            'remove_logo'=>['nullable','boolean'],'remove_favicon'=>['nullable','boolean'],'remove_social_image'=>['nullable','boolean'],
        ]);
        $data['maintenance_mode']=$request->boolean('maintenance_mode');
        $removeLogo=$request->boolean('remove_logo');
        $removeFavicon=$request->boolean('remove_favicon');
        $removeSocialImage=$request->boolean('remove_social_image');
        if ($removeLogo && $request->hasFile('logo_file')) {
            return back()->withErrors(['logo_file'=>'Logoyu aynı işlemde hem değiştiremez hem kaldıramazsınız.'])->withInput();
        }
        if ($removeFavicon && $request->hasFile('favicon_file')) {
            return back()->withErrors(['favicon_file'=>'Faviconu aynı işlemde hem değiştiremez hem kaldıramazsınız.'])->withInput();
        }
        if ($removeSocialImage && $request->hasFile('social_image_file')) {
            return back()->withErrors(['social_image_file'=>'Sosyal paylaşım görselini aynı işlemde hem değiştiremez hem kaldıramazsınız.'])->withInput();
        }

        $newPaths=[];
        try {
            foreach (['logo_file'=>'logo_path','favicon_file'=>'favicon_path','social_image_file'=>'social_image_path'] as $uploadKey=>$settingKey) {
                if (!$request->hasFile($uploadKey)) continue;
                $file=$request->file($uploadKey);
                $mime=(string)$file->getMimeType();
                $extension=match ($mime) {'image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp',default=>null};
                abort_if($extension===null,422,'Marka görseli türü desteklenmiyor. PNG, JPEG veya WebP kullanın.');
                $path=$file->storeAs('orvionart-brand', $settingKey.'-'.Str::random(40).'.'.$extension, 'local');
                if (!$path) throw new \RuntimeException('Marka görseli özel depolamaya kaydedilemedi.');
                $newPaths[$settingKey]=$path;
            }

            // Ensure the singleton row exists before acquiring its transaction lock.
            // Settings, file references and the audit event commit or roll back together.
            SiteSetting::firstOrCreate(['setting_key' => 'general'], ['setting_value' => $this->defaultSettings()]);
            $oldPaths=[];
            DB::transaction(function () use ($request, $data, $newPaths, $removeLogo, $removeFavicon, $removeSocialImage, &$oldPaths): void {
                $setting = SiteSetting::query()->where('setting_key', 'general')->lockForUpdate()->firstOrFail();
                $current = is_array($setting->setting_value) ? $setting->setting_value : [];
                $merged=array_merge($this->defaultSettings(), $current, $data);
                unset($merged['logo_file'], $merged['favicon_file'], $merged['social_image_file'], $merged['remove_logo'], $merged['remove_favicon'], $merged['remove_social_image']);

                foreach (['logo_path'=>$removeLogo,'favicon_path'=>$removeFavicon,'social_image_path'=>$removeSocialImage] as $key=>$remove) {
                    if (isset($newPaths[$key]) || $remove) {
                        if (!empty($current[$key]) && is_string($current[$key])) $oldPaths[]=$current[$key];
                        $merged[$key]=isset($newPaths[$key]) ? $newPaths[$key] : null;
                    } else {
                        // Do not allow ordinary/legacy settings forms to erase file references.
                        $merged[$key]=$current[$key] ?? null;
                    }
                }
                $setting->setting_value=$merged;
                $setting->save();
                $this->audit($request, 'site_settings.updated', null, ['fields' => array_values(array_unique(array_merge(array_keys($data), array_keys($newPaths), $removeLogo?['logo_removed']:[], $removeFavicon?['favicon_removed']:[], $removeSocialImage?['social_image_removed']:[])))]);
            });

            // Remove superseded private files only after the new references and audit
            // event have committed. A failed DB transaction leaves the old brand intact.
            $currentBrandSettings=SiteSetting::getValue('general');
            $stillReferenced=array_filter([$currentBrandSettings['logo_path']??null,$currentBrandSettings['favicon_path']??null,$currentBrandSettings['social_image_path']??null], 'is_string');
            foreach (array_unique($oldPaths) as $oldPath) {
                if (is_string($oldPath) && preg_match('~^orvionart-brand/(?:logo_path|favicon_path|social_image_path)-[A-Za-z0-9]{40}\.(?:png|jpg|webp)$~', $oldPath) === 1 && !in_array($oldPath, $stillReferenced, true)) Storage::disk('local')->delete($oldPath);
            }
        } catch (\Throwable $exception) {
            foreach ($newPaths as $newPath) Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        return back()->with('success','Genel site ayarları kaydedildi. Marka görselleri güncellendiyse yayınlanan sitede de kullanılacaktır; ödeme veya Demo Engine durumu değiştirilmedi.');
    }

    public function uploadMedia(Request $request, SitePage $sitePage): RedirectResponse
    {
        $data=$request->validate(['file'=>['required','file','max:30720','mimetypes:image/jpeg,image/png,image/webp,image/gif'],'title'=>['nullable','string','max:160'],'alt_text'=>['nullable','string','max:250']]);
        $file=$data['file']; $mime=(string)$file->getMimeType();
        $path=$file->store('orvionart-site-media/'.$sitePage->id.'/'.now()->format('Y/m'),'local');
        if (!$path) return back()->withErrors(['file'=>'Görsel özel depolamaya kaydedilemedi.']);
        try {
            // Keep the media row and its audit event atomic. If audit persistence
            // fails, the row must roll back before the already-written file is
            // removed; otherwise a failed upload could leave a dangling DB row.
            DB::transaction(function () use ($request, $sitePage, $data, $file, $mime, $path): void {
                $asset=MediaAsset::create(['experience_id'=>null,'site_page_id'=>$sitePage->id,'uploaded_by'=>$request->user()->id,
                    'title'=>trim((string)($data['title']??'')) ?: pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME),
                    'alt_text'=>trim((string)($data['alt_text']??'')) ?: null,'disk'=>'local','path'=>$path,
                    'original_name'=>Str::limit(basename($file->getClientOriginalName()),255,''),'mime_type'=>$mime,'kind'=>'image','file_size'=>(int)$file->getSize()]);
                $this->audit($request,'site_page.media_uploaded',$asset,['site_page_id'=>$sitePage->id,'file_size'=>$asset->file_size]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            throw $e;
        }
        return back()->with('success','Görsel bu sayfanın Site Builder medya alanına yüklendi. Yayındaki sayfada kullanılabilmesi için taslağa ekleyip yeniden yayınlayın.');
    }

    public function navigation(): View
    {
        return view('admin.site-builder.navigation',['items'=>SiteNavigationItem::with('page')->orderBy('location')->orderBy('sort_order')->orderBy('id')->get(),'pages'=>SitePage::where('status','published')->whereNotNull('published_at')->where('published_at','<=',now())->orderBy('title')->get(['id','title','slug','status','show_in_menu','published_at','published_title','published_slug','published_show_in_menu'])]);
    }

    public function saveNavigation(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'label'=>['required','string','max:100'],'location'=>['required',Rule::in(['header','footer'])],
            'site_page_id'=>['nullable','integer',Rule::exists('site_pages','id')],
            'external_url'=>['nullable','string','max:500','url','regex:~^https://~i'],
            'sort_order'=>['nullable','integer','min:0','max:100000'],
        ],['external_url.regex'=>'Haricî bağlantılar yalnızca HTTPS olmalıdır.']);
        if (empty($data['site_page_id']) && empty($data['external_url'])) return back()->withErrors(['site_page_id'=>'Bir sayfa veya HTTPS haricî bağlantı seçin.'])->withInput();
        if (!empty($data['site_page_id']) && !empty($data['external_url'])) return back()->withErrors(['external_url'=>'Sayfa bağlantısı ve haricî bağlantı aynı anda seçilemez.'])->withInput();
        if (!empty($data['site_page_id'])) { $targetPage=SitePage::findOrFail($data['site_page_id']); if (!$targetPage->isPubliclyAvailable()) return back()->withErrors(['site_page_id'=>'Taslak sayfalar menü bağlantısı olarak eklenemez. Önce sayfayı yayınlayın.'])->withInput(); if ($data['location']==='header' && !$targetPage->published_show_in_menu) return back()->withErrors(['site_page_id'=>'Ana menüye eklemek için sayfa ayarlarında “menüde gösterilmeye uygun” seçeneğini etkinleştirin.'])->withInput(); }
        DB::transaction(function () use ($request, $data): void {
            if (!empty($data['site_page_id'])) {
                // Re-check the destination while holding a row lock; the preflight
                // validation alone can become stale if another admin unpublishes it.
                $targetPage = SitePage::query()->whereKey($data['site_page_id'])->lockForUpdate()->firstOrFail();
                abort_unless($targetPage->isPubliclyAvailable(), 422, 'Taslak sayfalar menü bağlantısı olarak eklenemez. Önce sayfayı yayınlayın.');
                abort_if($data['location'] === 'header' && !$targetPage->published_show_in_menu, 422, 'Ana menüye eklemek için sayfa ayarlarında “menüde gösterilmeye uygun” seçeneğini etkinleştirin.');
            }
            SiteNavigationItem::create($data + ['is_active' => true, 'sort_order' => $data['sort_order'] ?? 0]);
            $this->audit($request, 'site_navigation.created', null, ['label' => $data['label'], 'location' => $data['location']]);
        });
        return back()->with('success','Menü bağlantısı eklendi.');
    }

    public function updateNavigation(Request $request, SiteNavigationItem $item): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'location' => ['required', Rule::in(['header', 'footer'])],
            'site_page_id' => ['nullable', 'integer', Rule::exists('site_pages', 'id')],
            'external_url' => ['nullable', 'string', 'max:500', 'url', 'regex:~^https://~i'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ], ['external_url.regex' => 'Haricî bağlantılar yalnızca HTTPS olmalıdır.']);

        $pageId = $data['site_page_id'] ?? null;
        $externalUrl = trim((string) ($data['external_url'] ?? ''));
        if (!$pageId && $externalUrl === '') {
            return back()->withErrors(['site_page_id' => 'Bir yayınlanmış sayfa veya HTTPS haricî bağlantı seçin.'])->withInput();
        }
        if ($pageId && $externalUrl !== '') {
            return back()->withErrors(['external_url' => 'Sayfa bağlantısı ve haricî bağlantı aynı anda kullanılamaz.'])->withInput();
        }

        $updated = DB::transaction(function () use ($request, $item, $data, $pageId, $externalUrl): bool {
            $lockedItem = SiteNavigationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $targetPage = null;

            if ($pageId) {
                $targetPage = SitePage::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
                if (!$targetPage->isPubliclyAvailable()) {
                    return false;
                }
                if ($data['location'] === 'header' && !$targetPage->published_show_in_menu) {
                    return false;
                }
            } elseif (preg_match('~^https://[^\s]+$~i', $externalUrl) !== 1) {
                return false;
            }

            $before = [
                'label' => $lockedItem->label,
                'location' => $lockedItem->location,
                'site_page_id' => $lockedItem->site_page_id,
                'external_url' => $lockedItem->external_url,
                'sort_order' => $lockedItem->sort_order,
            ];
            $lockedItem->label = trim($data['label']);
            $lockedItem->location = $data['location'];
            $lockedItem->site_page_id = $pageId ? (int) $pageId : null;
            $lockedItem->external_url = $pageId ? null : $externalUrl;
            $lockedItem->sort_order = (int) $data['sort_order'];
            $lockedItem->save();

            $this->audit($request, 'site_navigation.updated', $lockedItem, [
                'before' => $before,
                'after' => [
                    'label' => $lockedItem->label,
                    'location' => $lockedItem->location,
                    'site_page_id' => $lockedItem->site_page_id,
                    'external_url' => $lockedItem->external_url,
                    'sort_order' => $lockedItem->sort_order,
                ],
            ]);
            return true;
        });

        if (!$updated) {
            return back()->withErrors([
                'navigation' => 'Bağlantı güncellenemedi. Hedef sayfanın yayında ve ana menü için uygun olduğunu ya da haricî adresin HTTPS kullandığını doğrulayın.',
            ])->withInput();
        }

        return back()->with('success', 'Menü bağlantısı güncellendi.');
    }

    public function toggleNavigation(Request $request, SiteNavigationItem $item): RedirectResponse
    {
        $activated = DB::transaction(function () use ($request, $item): ?bool {
            $lockedItem = SiteNavigationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $nextActive = !$lockedItem->is_active;

            // Do not enable a dead/internal link. Re-check the page while holding
            // its row lock so a concurrent unpublish cannot make this check stale.
            // Deactivation remains allowed even when the target is already unavailable.
            if ($nextActive) {
                if ($lockedItem->site_page_id !== null) {
                    $targetPage = SitePage::query()->whereKey($lockedItem->site_page_id)->lockForUpdate()->first();
                    $canActivate = $targetPage !== null
                        && $targetPage->isPubliclyAvailable()
                        && ($lockedItem->location !== 'header' || (bool) $targetPage->published_show_in_menu);
                } else {
                    $externalUrl = trim((string) $lockedItem->external_url);
                    $canActivate = preg_match('~^https://[^\s]+$~i', $externalUrl) === 1;
                }

                if (!$canActivate) {
                    return null;
                }
            }

            $lockedItem->is_active = $nextActive;
            $lockedItem->save();
            $this->audit($request, 'site_navigation.toggled', $lockedItem, ['is_active' => $lockedItem->is_active]);
            return $lockedItem->is_active;
        });

        if ($activated === null) {
            return back()->withErrors([
                'navigation' => 'Bu bağlantı etkinleştirilemedi. Hedef sayfanın yayında ve ana menü için uygun olduğunu ya da haricî bağlantının HTTPS kullandığını doğrulayın.',
            ]);
        }

        return back()->with('success','Menü bağlantısı durumu güncellendi.');
    }

    public function moveNavigation(Request $request, SiteNavigationItem $item): RedirectResponse
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        DB::transaction(function () use ($request, $item, $data): void {
            // Lock the full ordered group and use its fresh row state rather than
            // the potentially stale route-bound model for the reorder operation.
            $siblings = SiteNavigationItem::query()->where('location', $item->location)
                ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $index = $siblings->search(fn ($row) => (int) $row->id === (int) $item->id);
            if ($index === false) return;
            $targetIndex = $data['direction'] === 'up' ? $index - 1 : $index + 1;
            if (!isset($siblings[$targetIndex])) return;
            $ordered = $siblings->values();
            $first = $ordered[$index]; $second = $ordered[$targetIndex];
            $ordered[$index] = $second; $ordered[$targetIndex] = $first;
            foreach ($ordered as $position => $row) {
                SiteNavigationItem::whereKey($row->id)->update(['sort_order' => $position * 10, 'updated_at' => now()]);
            }
            $this->audit($request, 'site_navigation.reordered', $item, ['location' => $item->location, 'direction' => $data['direction']]);
        });
        return back()->with('success', 'Menü sırası güncellendi.');
    }

    public function deleteNavigation(Request $request, SiteNavigationItem $item): RedirectResponse
    {
        DB::transaction(function () use ($request, $item): void {
            $lockedItem = SiteNavigationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $label = $lockedItem->label;
            $lockedItem->delete();
            $this->audit($request, 'site_navigation.deleted', null, ['label' => $label, 'item_id' => $item->id]);
        });
        return back()->with('success','Menü bağlantısı silindi.');
    }

    private function form(SitePage $page,string $title,string $action,string $method): View
    {
        return view('admin.site-builder.form',['page'=>$page,'pageTitle'=>$title,'formAction'=>$action,'formMethod'=>$method,
            'pageTypes'=>['home'=>'Ana Sayfa','standard'=>'Standart','qr-solutions'=>'QR Çözümleri','nfc-solutions'=>'NFC Çözümleri','about'=>'Hakkımızda','contact'=>'İletişim','faq'=>'SSS','legal'=>'Yasal Bilgilendirme'],
            'assets'=>MediaAsset::whereNotNull('site_page_id')->where('site_page_id',$page->id)->where('kind','image')->orderByDesc('id')->get(),
            'sectionTypes'=>['hero'=>'Hero / Başlık','hero_slider'=>'Hero Slider','text'=>'Metin','image_text'=>'Görsel + Metin','cards'=>'Kart Listesi','features'=>'Özellikler','steps'=>'Adım Adım','faq'=>'SSS','cta'=>'Çağrı / Buton','contact'=>'İletişim Çağrısı','experience_list'=>'Yayındaki Deneyimler','qr_nfc_promo'=>'QR / NFC Tanıtımı','demo_promo'=>'Demo Tanıtımı','free_plan_promo'=>'Ücretsiz Kullanım Tanıtımı','testimonials'=>'Müşteri Görüşleri','stats'=>'İstatistikler']]);
    }

    private function validated(Request $request, ?SitePage $page=null): array
    {
        $data=$request->validate([
            'title'=>['required','string','max:180'],'slug'=>['required','string','max:180','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',Rule::unique('site_pages','slug')->ignore($page?->id)],
            'page_type'=>['required',Rule::in(SitePage::TYPES)],'show_in_menu'=>['nullable','boolean'],
            'seo_title'=>['nullable','string','max:180'],'meta_description'=>['nullable','string','max:320'],
            'canonical_url'=>['nullable','url','max:500','regex:~^https://~i'],'noindex'=>['nullable','boolean'],
            'social_image_asset_id'=>['nullable','integer'],
            'sections'=>['nullable','array','max:40'],'sections.*.type'=>['required','string',Rule::in(SitePage::SECTION_TYPES)],
            'sections.*.title'=>['nullable','string','max:180'],'sections.*.subtitle'=>['nullable','string','max:240'],
            'sections.*.body'=>['nullable','string','max:12000'],'sections.*.image_asset_id'=>['nullable','integer'],
            'sections.*.image_alt'=>['nullable','string','max:250'],'sections.*.cta_label'=>['nullable','string','max:80'],
            'sections.*.cta_url'=>['nullable','string','max:500'],'sections.*.variant'=>['nullable',Rule::in(['default','soft','dark','accent'])],
            'sections.*.items_text'=>['nullable','string','max:12000'],'sections.*.is_active'=>['nullable','boolean'],
        ]);
        $slug=$data['slug'];
        if (($data['page_type'] ?? '') === 'home' && $slug !== 'home') throw \Illuminate\Validation\ValidationException::withMessages(['slug'=>'Ana sayfa türünün adresi sabit olarak home olmalıdır.']);
        if ($slug === 'home' && ($data['page_type'] ?? '') !== 'home') throw \Illuminate\Validation\ValidationException::withMessages(['page_type'=>'home adresi yalnızca Ana Sayfa türünde kullanılabilir.']);
        $reserved=['admin','api','go','media','demo','health','robots','p','login','logout','sitemap'];
        if (in_array($slug,$reserved,true) || str_starts_with($slug,'admin-')) throw \Illuminate\Validation\ValidationException::withMessages(['slug'=>'Bu adres sistem rotaları için ayrılmıştır.']);
        $socialAssetId = $data['social_image_asset_id'] ?? null;
        if ($socialAssetId && !MediaAsset::where('id', $socialAssetId)->where('site_page_id', $page?->id)->where('kind', 'image')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['social_image_asset_id' => 'Sosyal paylaşım görseli bu sayfanın medya alanında bulunmalıdır.']);
        }
        $sections=[];
        foreach (($data['sections']??[]) as $index=>$section) {
            $items=[];
            $itemLines = array_values(array_filter(
                array_map('trim', preg_split('/\R/u', (string) ($section['items_text'] ?? '')) ?: []),
                static fn (string $line): bool => $line !== ''
            ));
            // The same 100-item ceiling is enforced for new saves and revision restores.
            if (count($itemLines) > 100) {
                throw \Illuminate\Validation\ValidationException::withMessages(["sections.$index.items_text"=>'Bir bölümde en fazla 100 öğe kullanılabilir.']);
            }
            foreach ($itemLines as $line) {
                $parts=array_map('trim',explode('|',$line));
                $itemTitle=$parts[0]??''; $itemText=$parts[1]??'';
                if (mb_strlen($itemTitle) > 180 || mb_strlen($itemText) > 12000) {
                    throw \Illuminate\Validation\ValidationException::withMessages(["sections.$index.items_text"=>'Öğe başlığı en fazla 180, açıklaması en fazla 12.000 karakter olabilir.']);
                }
                $item=['title'=>$itemTitle,'text'=>$itemText];
                if (($section['type']??'')==='hero_slider') {
                    if (isset($parts[2]) && $parts[2]!=='' && (!ctype_digit($parts[2]) || (int)$parts[2]<1)) throw \Illuminate\Validation\ValidationException::withMessages(["sections.$index.items_text"=>'Slider görsel IDsi pozitif bir sayı olmalıdır.']);
                    $item['image_asset_id']=isset($parts[2]) && $parts[2]!=='' ? (int)$parts[2] : null;
                    $item['cta_label']=$parts[3]??''; $item['cta_url']=$parts[4]??'';
                    if (mb_strlen($item['cta_label']) > 80 || mb_strlen($item['cta_url']) > 500) {
                        throw \Illuminate\Validation\ValidationException::withMessages(["sections.$index.items_text"=>'Slider buton metni en fazla 80, bağlantısı en fazla 500 karakter olabilir.']);
                    }
                    if (!$this->isSafeSnapshotLink($item['cta_url'])) throw \Illuminate\Validation\ValidationException::withMessages(["sections.$index.items_text"=>'Slider bağlantısı geçerli HTTPS, site içi / veya # adresi olmalıdır.']);
                    if ($item['image_asset_id'] && !MediaAsset::where('id',$item['image_asset_id'])->where('site_page_id',$page?->id)->where('kind','image')->exists()) throw \Illuminate\Validation\ValidationException::withMessages(["sections.$index.items_text"=>'Slider görseli bu sayfanın medya alanındaki bir görsel IDsi olmalıdır.']);
                }
                $items[]=$item;
            }
            $url=trim((string)($section['cta_url']??''));
            if (mb_strlen($url) > 500 || !$this->isSafeSnapshotLink($url)) throw \Illuminate\Validation\ValidationException::withMessages(["sections.$index.cta_url"=>'Buton bağlantısı geçerli HTTPS, site içi / veya # adresi olmalıdır; en fazla 500 karakter kullanılabilir.']);
            $assetId=$section['image_asset_id']??null;
            if ($assetId && !MediaAsset::where('id',$assetId)->where('site_page_id',$page?->id)->where('kind','image')->exists()) throw \Illuminate\Validation\ValidationException::withMessages(["sections.$index.image_asset_id"=>'Görsel bu sayfanın Site Builder medya alanında bulunamadı.']);
            $sections[]=['type'=>$section['type'],'title'=>trim((string)($section['title']??'')),'subtitle'=>trim((string)($section['subtitle']??'')),
                'body'=>trim((string)($section['body']??'')),'image_asset_id'=>$assetId ? (int)$assetId : null,
                'image_alt'=>trim((string)($section['image_alt']??'')),'cta_label'=>trim((string)($section['cta_label']??'')),
                'cta_url'=>$url,'variant'=>$section['variant']??'default','items'=>$items,'is_active'=>isset($section['is_active']) ? (bool)$section['is_active'] : true];
        }
        $data['sections']=$sections; $data['social_image_asset_id']=$socialAssetId ? (int)$socialAssetId : null; $data['show_in_menu']=$request->boolean('show_in_menu'); $data['noindex']=$request->boolean('noindex',true);
        unset($data['status']); return $data;
    }

    /**
     * Lock every media row owned by a page in deterministic order before locking
     * the page row. Media deletion locks the same asset row before checking page
     * references, so the two operations cannot pass each other's reference checks.
     */
    private function lockPageMediaAssets(int $pageId): void
    {
        MediaAsset::query()
            ->where('site_page_id', $pageId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function validateSocialImage(SitePage $page, mixed $assetId, string $message): void
    {
        if ($assetId === null || $assetId === '' || (int) $assetId === 0) {
            return;
        }

        abort_unless(
            MediaAsset::query()
                ->whereKey((int) $assetId)
                ->where('site_page_id', $page->id)
                ->where('kind', 'image')
                ->exists(),
            422,
            $message,
        );
    }

    private function validateSectionMedia(SitePage $page,array $sections): void
    {
        foreach ($sections as $section) {
            if (!empty($section['image_asset_id'])) abort_unless(MediaAsset::where('id',$section['image_asset_id'])->where('site_page_id',$page->id)->where('kind','image')->exists(),422,'Sayfada kullanılan bir görsel bulunamadı. Yayınlama/geri alma iptal edildi.');
            foreach (($section['items']??[]) as $item) {
                if (!empty($item['image_asset_id'])) abort_unless(MediaAsset::where('id',$item['image_asset_id'])->where('site_page_id',$page->id)->where('kind','image')->exists(),422,'Slider bölümündeki bir görsel bulunamadı. Yayınlama/geri alma iptal edildi.');
            }
        }
    }

    private function revision(Request $request,SitePage $page,string $event,string $summary): void
    {
        SitePageRevision::create(['site_page_id'=>$page->id,'version'=>$page->version,'event'=>$event,'change_summary'=>$summary,'snapshot'=>$page->snapshot(),'actor_id'=>$request->user()->id,'created_at'=>now()]);
    }
    private function audit(Request $request,string $action,$subject,array $metadata): void
    {
        AuditLog::create(['actor_id'=>$request->user()->id,'action'=>$action,'subject_type'=>$subject?get_class($subject):SitePage::class,'subject_id'=>$subject?->id,'ip_address'=>$request->ip(),'user_agent'=>Str::limit((string)$request->userAgent(),500),'metadata'=>$metadata,'created_at'=>now()]);
    }
    private function publicNavigation(): array
    {
        return SiteNavigationItem::with('page')->where('is_active',true)->orderBy('sort_order')->get()->filter(fn($item)=>$item->resolvedUrl()!==null)->groupBy('location')->all();
    }
    private function defaultSettings(): array
    {
        return [
            'site_name'=>'ORVIONART','brand_text'=>'ORVIONART',
            'primary_color'=>'#10202c','accent_color'=>'#58d3cb',
            'background_color'=>'#f7f9fb','surface_color'=>'#ffffff',
            'font_family'=>'system','heading_font_family'=>'system','body_font_family'=>'system',
            'button_style'=>'pill','header_behavior'=>'normal','footer_layout'=>'split',
            'content_width'=>1160,'default_seo_title'=>'ORVIONART — Dijital Deneyim Platformu',
            'default_meta_description'=>'QR ve NFC ile erişilen dijital deneyimler.',
            'contact_email'=>'','contact_phone'=>'','maintenance_mode'=>false,
            'maintenance_message'=>'Sitemiz kısa süreli bakımda. Lütfen daha sonra tekrar deneyin.',
            'footer_text'=>'© ORVIONART','logo_path'=>null,'favicon_path'=>null,'social_image_path'=>null,
        ];
    }
}
