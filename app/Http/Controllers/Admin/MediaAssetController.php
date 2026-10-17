<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessUploadedVideo;
use App\Models\AuditLog;
use App\Models\Experience;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MediaAssetController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', Rule::in(['image', 'video'])],
            'location' => ['nullable', Rule::in(['all', 'library', 'experience', 'site'])],
        ]);
        $term = trim((string) ($data['q'] ?? ''));
        $location = $data['location'] ?? 'all';

        $assets = MediaAsset::query()
            ->with(['experience:id,title,status', 'sitePage:id,title,status,published_slug'])
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('title', 'like', "%{$term}%")
                        ->orWhere('original_name', 'like', "%{$term}%")
                        ->orWhere('alt_text', 'like', "%{$term}%");
                });
            })
            ->when(!empty($data['kind']), fn ($query) => $query->where('kind', $data['kind']))
            ->when($location === 'library', fn ($query) => $query->whereNull('experience_id')->whereNull('site_page_id'))
            ->when($location === 'experience', fn ($query) => $query->whereNotNull('experience_id'))
            ->when($location === 'site', fn ($query) => $query->whereNotNull('site_page_id'))
            ->latest()
            ->paginate(18)
            ->withQueryString();

        $requestedExperienceId = filter_var($request->query('experience_id'), FILTER_VALIDATE_INT);
        $selectedExperienceId = $requestedExperienceId && Experience::query()->whereKey($requestedExperienceId)->exists()
            ? (int) $requestedExperienceId
            : null;

        return view('admin.media.index', [
            'assets' => $assets,
            'experiences' => Experience::query()->orderBy('title')->get(['id', 'title', 'status']),
            'selectedExperienceId' => $selectedExperienceId,
            'totalBytes' => (int) MediaAsset::sum('file_size'),
            'imageCount' => (int) MediaAsset::where('kind', 'image')->count(),
            'videoCount' => (int) MediaAsset::where('kind', 'video')->count(),
        ]);
    }

    /** Authenticated-only preview; private files are never made public just to show a thumbnail in the admin. */
    public function preview(MediaAsset $asset): BinaryFileResponse
    {
        abort_unless(in_array($asset->mime_type, [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/webm', 'video/ogg',
        ], true), 404);

        if ($asset->kind === 'video') {
            abort_unless($asset->processing_status === 'ready', 409, 'Video henüz teknik doğrulamadan geçmedi; işleme durumu: '.$asset->processing_status.'.');
        }

        // Videos prefer the verified H.264/MP4 derivative. Legacy assets without
        // a derivative keep their original path and MIME behavior.
        $deliveryPath = $asset->kind === 'video' && $asset->processed_path
            ? (string) $asset->processed_path
            : (string) $asset->path;
        $deliveryMime = $asset->kind === 'video' && $asset->processed_path
            ? 'video/mp4'
            : (string) $asset->mime_type;

        // The admin preview is not a general-purpose file reader. Only managed
        // media on the private local disk may be streamed; legacy disks and
        // database paths must not expose arbitrary files to an authenticated user.
        abort_unless($asset->disk === 'local' && $this->isSafeManagedMediaPath($deliveryPath), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($deliveryPath), 404);
        $rootPath = realpath($disk->path(''));
        $path = realpath($disk->path($deliveryPath));
        abort_unless(
            $rootPath !== false
            && $path !== false
            && str_starts_with($path, rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            && is_file($path),
            404
        );

        $headers = [
            'Content-Type' => $deliveryMime,
            'Content-Disposition' => 'inline; filename="orvionart-admin-media-' . $asset->id . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];
        $actualSize = filesize($path);
        if ($actualSize !== false) $headers['Content-Length'] = (string) $actualSize;

        $response = response()->file($path, $headers);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    /** Authenticated-only thumbnail delivery from the private managed-media area. */
    public function thumbnail(MediaAsset $asset): BinaryFileResponse
    {
        abort_unless($asset->kind === 'video' && $asset->processing_status === 'ready' && is_string($asset->thumbnail_path) && $asset->thumbnail_path !== '', 404);
        abort_unless($asset->disk === 'local' && $this->isSafeManagedMediaPath($asset->thumbnail_path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->thumbnail_path), 404);
        $rootPath = realpath($disk->path(''));
        $path = realpath($disk->path($asset->thumbnail_path));
        abort_unless($rootPath !== false && $path !== false
            && str_starts_with($path, rtrim($rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            && is_file($path), 404);
        abort_unless((new \finfo(FILEINFO_MIME_TYPE))->file($path) === 'image/jpeg', 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="orvionart-video-thumbnail-' . $asset->id . '.jpg"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:30720', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg'],
            'title' => ['nullable', 'string', 'max:160'],
            'alt_text' => ['nullable', 'string', 'max:250'],
            'experience_id' => ['nullable', 'integer', Rule::exists('experiences', 'id')->whereNull('deleted_at')],
        ], [
            'file.max' => 'Dosya en fazla 30 MB olabilir.',
            'file.mimetypes' => 'Yalnızca JPG, PNG, WebP, GIF, MP4, WebM veya OGG medya dosyaları kabul edilir. SVG ve çalıştırılabilir dosyalar kabul edilmez.',
        ]);

        $file = $data['file'];
        $mime = (string) $file->getMimeType();
        $kind = str_starts_with($mime, 'video/') ? 'video' : 'image';
        $path = $file->store('orvionart-media/' . now()->format('Y/m'), 'local');
        if (!$path) {
            return back()->withErrors(['file' => 'Dosya güvenli depolama alanına kaydedilemedi.'])->withInput();
        }

        try {
            $asset = DB::transaction(function () use ($data, $request, $file, $mime, $kind, $path): MediaAsset {
                $asset = MediaAsset::create([
                    'experience_id' => $data['experience_id'] ?? null,
                    'uploaded_by' => $request->user()->id,
                    'title' => trim((string) ($data['title'] ?? '')) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'alt_text' => trim((string) ($data['alt_text'] ?? '')) ?: null,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'mime_type' => $mime,
                    'kind' => $kind,
                    'file_size' => (int) $file->getSize(),
                    'processing_status' => $kind === 'video' ? 'queued' : 'ready',
                ]);
                AuditLog::create([
                    'actor_id' => $request->user()->id,
                    'action' => 'media.uploaded',
                    'subject_type' => MediaAsset::class,
                    'subject_id' => $asset->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500),
                    'metadata' => ['kind' => $kind, 'mime_type' => $mime, 'file_size' => $asset->file_size, 'experience_id' => $asset->experience_id, 'site_page_id' => $asset->site_page_id],
                    'created_at' => now(),
                ]);
                return $asset;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        if ($kind === 'video') {
            try {
                ProcessUploadedVideo::dispatch($asset->id);
            } catch (\Throwable $queueException) {
                MediaAsset::query()->whereKey($asset->id)->update([
                    'processing_status' => 'failed',
                    'processing_error' => 'Video işleme işi kuyruğa alınamadı. Kuyruk bağlantısı/worker ayarları kontrol edilmelidir.',
                    'updated_at' => now(),
                ]);
                Log::error('ORVIONART video processing job could not be queued.', [
                    'media_asset_id' => $asset->id,
                    'exception_class' => get_class($queueException),
                ]);
            }
        }

        $message = $kind === 'video'
            ? 'Video güvenli depolamaya alındı. Teknik doğrulama arka plan kuyruğunda; yalnızca doğrulanmış video yayınlanabilir.'
            : 'Medya dosyası güvenli depolama alanına yüklendi. Ziyaretçi sayfasında yalnızca bağlı deneyim yayınlanabilir durumdayken gösterilir.';

        if ($asset->experience_id !== null && Experience::query()->whereKey($asset->experience_id)->exists()) {
            return redirect()->route('admin.experiences.edit', $asset->experience_id)->with('success', $message);
        }
        return redirect()->route('admin.media.index')->with('success', $message);
    }

    /** Retry a failed video verification job without re-uploading the source bytes. */
    public function retryVideoProcessing(Request $request, MediaAsset $asset): RedirectResponse
    {
        DB::transaction(function () use ($request, $asset): void {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->kind === 'video' && $locked->disk === 'local', 404);
            abort_unless($locked->processing_status === 'failed', 409, 'Yalnızca başarısız video işleme yeniden denenebilir.');

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'media.video_processing_retry_requested',
                'subject_type' => MediaAsset::class,
                'subject_id' => $locked->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'metadata' => ['previous_status' => $locked->processing_status, 'processing_attempts' => $locked->processing_attempts],
                'created_at' => now(),
            ]);

            $locked->forceFill([
                'processing_status' => 'queued',
                'processing_error' => null,
                'processed_at' => null,
                'processed_path' => null,
                'thumbnail_path' => null,
            ])->save();
        });

        try {
            ProcessUploadedVideo::dispatch($asset->id);
        } catch (\Throwable $queueException) {
            MediaAsset::query()->whereKey($asset->id)->update([
                'processing_status' => 'failed',
                'processing_error' => 'Video işleme işi kuyruğa alınamadı. Kuyruk bağlantısı/worker ayarları kontrol edilmelidir.',
                'updated_at' => now(),
            ]);
            Log::error('ORVIONART video processing retry could not be queued.', [
                'media_asset_id' => $asset->id,
                'exception_class' => get_class($queueException),
            ]);
            return back()->withErrors(['video' => 'Video işleme kuyruğa alınamadı. Sunucu kuyruğu kontrol edilmelidir.']);
        }

        return redirect()->route('admin.media.index')->with('success', 'Video teknik doğrulama için yeniden kuyruğa alındı.');
    }

    /** Move an experience-linked asset back to the central library without deleting its bytes. */
    public function detachFromExperience(Request $request, MediaAsset $asset): RedirectResponse
    {
        $detached = DB::transaction(function () use ($request, $asset): bool {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($locked->experience_id === null) {
                return false;
            }

            $previousExperienceId = $locked->experience_id;
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'media.detached_from_experience',
                'subject_type' => MediaAsset::class,
                'subject_id' => $locked->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'metadata' => [
                    'experience_id' => $previousExperienceId,
                    'kind' => $locked->kind,
                    'file_size' => $locked->file_size,
                ],
                'created_at' => now(),
            ]);

            $locked->forceFill(['experience_id' => null])->save();
            return true;
        });

        if (!$detached) {
            return back()->withErrors(['media' => 'Bu dosya zaten merkezi kütüphanede.']);
        }

        return redirect()->route('admin.media.index')->with('success', 'Medya deneyimden ayrıldı ve dosya silinmeden merkezi kütüphaneye taşındı. Artık hiçbir herkese açık deneyim bu dosyayı göstermez.');
    }

    public function destroy(Request $request, MediaAsset $asset): RedirectResponse
    {
        if ($asset->experience_id !== null) {
            return back()->withErrors(['media' => 'Bu dosya bir deneyime bağlı. Önce “Kütüphaneye taşı” işlemiyle deneyim bağlantısını kaldırın.']);
        }
        if ($asset->isReferencedBySitePage()) {
            return back()->withErrors(['media' => 'Bu dosya Site Builder taslağında veya yayındaki sayfada kullanılıyor. Önce ilgili görsel referansını kaldırın, taslağı kaydedin ve gerekiyorsa sayfayı yeniden yayınlayın.']);
        }

        // Move bytes to a private quarantine before deleting the row. If the DB
        // transaction fails, restore the original path; never silently discard
        // the only copy just because a later filesystem operation fails.
        $quarantine = null;
        $derivativePaths = [];

        try {
            DB::transaction(function () use ($request, $asset, &$quarantine, &$derivativePaths): void {
                $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                if (!$locked->canBeDeletedSafely()) {
                    abort(409, 'Medya başka bir içerik tarafından kullanılıyor; silme işlemi durduruldu.');
                }

                $diskName = (string) $locked->disk;
                // The quarantine guarantee depends on the disk being private.
                // Never move a legacy/public-disk asset into a possibly web-served trash folder.
                if ($diskName !== 'local') {
                    abort(409, 'Medya özel yerel depolamada değil. Dosyanın yanlışlıkla herkese açılmasını önlemek için otomatik silme durduruldu; depolama kaydını inceleyin.');
                }

                $derivativePaths = array_values(array_filter([(string) ($locked->processed_path ?? ''), (string) ($locked->thumbnail_path ?? '')], fn (string $candidate): bool => $candidate !== '' && $this->isSafeManagedMediaPath($candidate)));

                $path = (string) $locked->path;
                if (!$this->isSafeManagedMediaPath($path)) {
                    abort(409, 'Medya dosya yolu güvenli yönetilen klasörlerden birinde değil; silme işlemi durduruldu.');
                }

                $disk = Storage::disk($diskName);
                if (!$disk->exists($path)) {
                    abort(409, 'Medya kaydı var ancak dosya depolamada bulunamadı. Kayıt otomatik silinmedi; önce medya bütünlük raporunu inceleyin.');
                }

                $quarantinePath = 'orvionart-trash/' . now()->format('Y/m/d') . '/' . Str::uuid() . '-' . basename($path);
                if (!$disk->move($path, $quarantinePath)) {
                    throw new \RuntimeException('Medya dosyası güvenli karantina alanına taşınamadı; silme işlemi durduruldu.');
                }
                $quarantine = ['disk' => $diskName, 'original_path' => $path, 'quarantine_path' => $quarantinePath];

                AuditLog::create([
                    'actor_id' => $request->user()->id,
                    'action' => 'media.deleted',
                    'subject_type' => MediaAsset::class,
                    'subject_id' => $locked->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500),
                    'metadata' => ['kind' => $locked->kind, 'file_size' => $locked->file_size, 'deletion_strategy' => 'private_quarantine'],
                    'created_at' => now(),
                ]);
                if (!$locked->delete()) {
                    throw new \RuntimeException('Medya veritabanı kaydı silinemedi; işlem geri alınıyor.');
                }
            });
        } catch (\Throwable $exception) {
            if (is_array($quarantine)) {
                try {
                    $disk = Storage::disk($quarantine['disk']);
                    if ($disk->exists($quarantine['quarantine_path']) && !$disk->exists($quarantine['original_path'])) {
                        if (!$disk->move($quarantine['quarantine_path'], $quarantine['original_path'])) {
                            logger()->critical('ORVIONART medya silme geri alma dosyası asıl konumuna taşınamadı.', [
                                'disk' => $quarantine['disk'],
                                'media_asset_id' => $asset->id,
                            ]);
                        }
                    }
                } catch (\Throwable $restoreException) {
                    logger()->critical('ORVIONART medya silme geri alma işlemi başarısız.', [
                        'media_asset_id' => $asset->id,
                        'exception_class' => get_class($restoreException),
                    ]);
                }
            }
            throw $exception;
        }

        // The DB commit has succeeded. A failed purge leaves a private file in
        // quarantine, which the read-only media storage inspector reports.
        $quarantineDisk = Storage::disk($quarantine['disk']);
        $purged = $quarantineDisk->delete($quarantine['quarantine_path']);
        foreach ($derivativePaths as $derivativePath) {
            try {
                if ($quarantineDisk->exists($derivativePath) && !$quarantineDisk->delete($derivativePath)) {
                    logger()->warning('ORVIONART medya türetilmiş dosyası özel depolamada kaldı.', ['media_asset_id' => $asset->id, 'path_type' => $derivativePath === ($asset->processed_path ?? null) ? 'processed' : 'thumbnail']);
                }
            } catch (\Throwable $derivativeCleanupException) {
                logger()->warning('ORVIONART medya türetilmiş dosyası temizlenemedi.', ['media_asset_id' => $asset->id, 'exception_class' => get_class($derivativeCleanupException)]);
            }
        }
        if (!$purged && $quarantineDisk->exists($quarantine['quarantine_path'])) {
            logger()->warning('ORVIONART silinmiş medya dosyası karantinada kaldı; inceleme gerekiyor.', [
                'disk' => $quarantine['disk'],
                'media_asset_id' => $asset->id,
            ]);
            return redirect()->route('admin.media.index')->with('warning', 'Medya kaydı silindi ancak dosya özel karantina alanında kaldı. Medya depolama denetimini çalıştırın.');
        }

        return redirect()->route('admin.media.index')->with('success', 'Medya dosyası ve dosya kaydı güvenli biçimde silindi.');
    }

    private function isSafeManagedMediaPath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\\0") || str_contains($path, '\\')) {
            return false;
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            return false;
        }

        return str_starts_with($path, 'orvionart-media/')
            || str_starts_with($path, 'orvionart-site-media/');
    }

}
