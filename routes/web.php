<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DemoEngineController as AdminDemoEngineController;
use App\Http\Controllers\PublicSite\DemoEngineController as PublicDemoEngineController;
use App\Http\Controllers\Admin\ExperienceController;
use App\Http\Controllers\Admin\MediaAssetController;
use App\Http\Controllers\Admin\VideoUploadController;
use App\Http\Controllers\Admin\QrLinkController;
use App\Http\Controllers\Admin\SiteBuilderController;
use App\Http\Controllers\Admin\UsageRightController;
use App\Http\Controllers\PublicSite\SitePageController;
use App\Http\Controllers\PublicSite\BrandAssetController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\PublicSite\ExperiencePlayerController;
use App\Http\Controllers\PublicSite\MediaDeliveryController;
use App\Http\Controllers\PublicSite\QrRedirectController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', [SitePageController::class, 'home'])->name('site.home');
Route::get('/brand/{type}', [BrandAssetController::class, 'show'])->whereIn('type', ['logo', 'favicon', 'social-image'])->name('brand.asset');

// Stable public identifier keeps QR/NFC destinations valid when the readable slug changes.
Route::get('/go/{token}', QrRedirectController::class)->where('token', '[A-Za-z0-9]{40}')->name('qr.redirect');
Route::get('/media/{asset}', [MediaDeliveryController::class, 'show'])->whereNumber('asset')->name('media.public');
Route::get('/media/{asset}/thumbnail', [MediaDeliveryController::class, 'thumbnail'])->whereNumber('asset')->name('media.thumbnail');
Route::get('/demo', [PublicDemoEngineController::class, 'show'])->name('demo.show');
Route::post('/demo/restart', [PublicDemoEngineController::class, 'restart'])->middleware('throttle:10,1')->name('demo.restart');
Route::get('/demo/media/{content}', [PublicDemoEngineController::class, 'media'])->whereNumber('content')->middleware('throttle:60,1')->name('demo.media');

Route::get('/p/{publicId}/{slug?}', [ExperiencePlayerController::class, 'show'])
    ->where('publicId', '[A-Za-z0-9]{40}')
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('experiences.public');

Route::get('/sitemap.xml', [SitePageController::class, 'sitemap'])->name('sitemap');

Route::get('/robots.txt', function () {
    $indexingEnabled = config('app.env') === 'production' && (bool) config('orvionart.public_indexing_enabled', false);
    $body = $indexingEnabled
        ? "User-agent: *\nAllow: /\nSitemap: " . rtrim((string) config('app.url'), '/') . "/sitemap.xml\n"
        : "User-agent: *\nDisallow: /\n";
    return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
})->name('robots');

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok', 'app' => 'ORVIONART', 'version' => trim((string) file_get_contents(base_path('VERSION'))), 'checks' => ['application' => 'ok', 'database' => 'ok']], 200);
    } catch (Throwable $e) {
        report($e);
        return response()->json(['status' => 'degraded', 'app' => 'ORVIONART', 'checks' => ['application' => 'ok', 'database' => 'unavailable']], 503);
    }
})->name('health');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'store'])->middleware('throttle:admin-login')->name('admin.login.store');
});

Route::middleware(['auth', 'superadmin', 'admin.session'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::resource('experiences', ExperienceController::class)->except(['show']);
    Route::get('/site-builder', [SiteBuilderController::class, 'index'])->name('site-builder.index');
    Route::get('/site-builder/create', [SiteBuilderController::class, 'create'])->name('site-builder.create');
    Route::post('/site-builder', [SiteBuilderController::class, 'store'])->name('site-builder.store');
    Route::get('/site-builder/settings', [SiteBuilderController::class, 'settings'])->name('site-builder.settings');
    Route::put('/site-builder/settings', [SiteBuilderController::class, 'saveSettings'])->name('site-builder.settings.save');
    Route::get('/site-builder/navigation', [SiteBuilderController::class, 'navigation'])->name('site-builder.navigation');
    Route::post('/site-builder/navigation', [SiteBuilderController::class, 'saveNavigation'])->name('site-builder.navigation.store');
    Route::patch('/site-builder/navigation/{item}', [SiteBuilderController::class, 'updateNavigation'])->name('site-builder.navigation.update');
    Route::patch('/site-builder/navigation/{item}/toggle', [SiteBuilderController::class, 'toggleNavigation'])->name('site-builder.navigation.toggle');
    Route::patch('/site-builder/navigation/{item}/move', [SiteBuilderController::class, 'moveNavigation'])->name('site-builder.navigation.move');
    Route::delete('/site-builder/navigation/{item}', [SiteBuilderController::class, 'deleteNavigation'])->name('site-builder.navigation.delete');
    Route::get('/site-builder/{sitePage}/edit', [SiteBuilderController::class, 'edit'])->name('site-builder.edit');
    Route::put('/site-builder/{sitePage}', [SiteBuilderController::class, 'update'])->name('site-builder.update');
    Route::post('/site-builder/{sitePage}/media', [SiteBuilderController::class, 'uploadMedia'])->middleware('throttle:20,1')->name('site-builder.media.upload');
    Route::get('/site-builder/{sitePage}/preview', [SiteBuilderController::class, 'preview'])->name('site-builder.preview');
    Route::post('/site-builder/{sitePage}/publish', [SiteBuilderController::class, 'publish'])->name('site-builder.publish');
    Route::post('/site-builder/{sitePage}/unpublish', [SiteBuilderController::class, 'unpublish'])->name('site-builder.unpublish');
    Route::get('/site-builder/{sitePage}/revisions', [SiteBuilderController::class, 'revisions'])->name('site-builder.revisions');
    Route::post('/site-builder/{sitePage}/revisions/{revision}/restore', [SiteBuilderController::class, 'restore'])->name('site-builder.restore');

    Route::get('/media', [MediaAssetController::class, 'index'])->name('media.index');
    Route::get('/media/{asset}/preview', [MediaAssetController::class, 'preview'])->whereNumber('asset')->name('media.preview');
    Route::get('/media/{asset}/thumbnail', [MediaAssetController::class, 'thumbnail'])->whereNumber('asset')->name('media.thumbnail');
    Route::post('/media/{asset}/retry-video-processing', [MediaAssetController::class, 'retryVideoProcessing'])->whereNumber('asset')->middleware('throttle:10,1')->name('media.video-processing.retry');
    Route::post('/media', [MediaAssetController::class, 'store'])->middleware('throttle:20,1')->name('media.store');
    Route::post('/media/video-uploads', [VideoUploadController::class, 'create'])->middleware('throttle:10,1')->name('video-uploads.create');
    Route::get('/media/video-uploads/{upload}', [VideoUploadController::class, 'status'])->whereNumber('upload')->name('video-uploads.status');
    Route::post('/media/video-uploads/{upload}/chunks/{index}', [VideoUploadController::class, 'chunk'])->whereNumber('upload')->whereNumber('index')->middleware('throttle:180,1')->name('video-uploads.chunk');
    Route::post('/media/video-uploads/{upload}/complete', [VideoUploadController::class, 'complete'])->whereNumber('upload')->middleware('throttle:10,1')->name('video-uploads.complete');
    Route::delete('/media/video-uploads/{upload}', [VideoUploadController::class, 'cancel'])->whereNumber('upload')->name('video-uploads.cancel');
    Route::patch('/media/{asset}/detach-experience', [MediaAssetController::class, 'detachFromExperience'])->name('media.detach-experience');
    Route::delete('/media/{asset}', [MediaAssetController::class, 'destroy'])->name('media.destroy');
    Route::get('/qr-links', [QrLinkController::class, 'index'])->name('qr-links.index');
    Route::get('/qr-links/export', [QrLinkController::class, 'export'])->middleware('throttle:10,1')->name('qr-links.export');
    Route::get('/usage-rights/export', [UsageRightController::class, 'export'])->middleware('throttle:10,1')->name('usage-rights.export');
    Route::get('/usage-rights', [UsageRightController::class, 'index'])->name('usage-rights.index');
    Route::post('/usage-rights', [UsageRightController::class, 'store'])->name('usage-rights.store');
    Route::patch('/usage-rights/{usageRight}/revoke', [UsageRightController::class, 'revoke'])->name('usage-rights.revoke');
    Route::get('/demo', [AdminDemoEngineController::class, 'index'])->name('demo.index');
    Route::put('/demo/settings', [AdminDemoEngineController::class, 'updateSettings'])->name('demo.settings.update');
    Route::post('/demo/contents', [AdminDemoEngineController::class, 'storeContent'])->middleware('throttle:20,1')->name('demo.contents.store');
    Route::patch('/demo/contents/{content}/toggle', [AdminDemoEngineController::class, 'toggleContent'])->name('demo.contents.toggle');
    Route::delete('/demo/contents/{content}', [AdminDemoEngineController::class, 'destroyContent'])->name('demo.contents.destroy');
    Route::post('/qr-links', [QrLinkController::class, 'store'])->name('qr-links.store');
    Route::get('/qr-links/{qrLink}/preview', [QrLinkController::class, 'preview'])->name('qr-links.preview');
    Route::get('/qr-links/{qrLink}/preview-image', [QrLinkController::class, 'previewImage'])->name('qr-links.preview-image');
    Route::get('/qr-links/{qrLink}/image', [QrLinkController::class, 'image'])->name('qr-links.image');
    Route::patch('/qr-links/{qrLink}/toggle', [QrLinkController::class, 'toggle'])->name('qr-links.toggle');
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');
});

// Public Site Builder pages; kept after all reserved/system routes.
Route::get('/s/{slug}', [SitePageController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('site.page');
