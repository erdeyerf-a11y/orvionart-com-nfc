<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DemoContent;
use App\Models\DemoEvent;
use App\Models\DemoSession;
use App\Models\DemoSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DemoEngineController extends Controller
{
    public function index(): View
    {
        $settings = DemoSetting::current();
        return view('admin.demo.index', [
            'settings' => $settings,
            'contents' => DemoContent::query()->withCount('assignments')->orderBy('sort_order')->orderBy('id')->paginate(12),
            'stats' => [
                'sessions' => DemoSession::count(),
                'activeSessions' => DemoSession::whereNull('expired_at')->where('ends_at', '>', now())->count(),
                'views' => DemoEvent::where('event_type', 'content_view')->count(),
                'activeContents' => DemoContent::where('is_active', true)->count(),
            ],
            'demoUrl' => route('demo.show'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'journey_days' => ['required', 'integer', 'min:1', 'max:365'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'title' => ['required', 'string', 'max:180'],
            'paused_message' => ['required', 'string', 'max:500'],
        ]);
        // Ensure the singleton exists before opening the write transaction; the
        // transaction then locks the actual row used by the state change.
        $settingsId = DemoSetting::current()->getKey();
        DB::transaction(function () use ($request, $data, $settingsId): void {
            // Lock the singleton settings row so concurrent admin updates cannot
            // overwrite one another based on a stale model instance.
            $settings = DemoSetting::query()->whereKey($settingsId)->lockForUpdate()->firstOrFail();
            $wasActive = $settings->is_active;
            $settings->fill($data);
            $settings->is_active = $request->boolean('is_active');
            $settings->save();

            // The state change and its audit event must commit or roll back together.
            $this->audit($request, $settings->is_active ? 'demo.engine.activated' : 'demo.engine.deactivated', $settings, [
                'journey_days' => $settings->journey_days,
                'retention_days' => $settings->retention_days,
                'previous_active' => $wasActive,
            ]);
        });

        return back()->with('success', 'Demo Engine ayarları kaydedildi. Ödeme sistemi bu işlemden etkilenmez.');
    }

    public function storeContent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
            'media' => ['nullable', 'file', 'max:30720', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg'],
        ], ['media.max' => 'Demo medyası en fazla 30 MB olabilir.', 'media.mimetypes' => 'Yalnızca desteklenen görsel ve video türleri kabul edilir.']);

        $path = null;
        $mime = null;
        $size = null;
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mime = (string) $file->getMimeType();
            $path = $file->store('orvionart-demo/' . now()->format('Y/m'), 'local');
            $size = (int) $file->getSize();
            if (!$path) return back()->withErrors(['media' => 'Demo medyası güvenli depolamaya kaydedilemedi.'])->withInput();
        }

        try {
            $content = DB::transaction(function () use ($request, $data, $path, $mime, $size): DemoContent {
                $content = DemoContent::create([
                    'created_by' => $request->user()->id,
                    'title' => trim($data['title']),
                    'summary' => trim((string) ($data['summary'] ?? '')) ?: null,
                    'body' => trim((string) ($data['body'] ?? '')) ?: null,
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'is_active' => $request->boolean('is_active', true),
                    'media_disk' => $path ? 'local' : null,
                    'media_path' => $path,
                    'media_mime_type' => $mime,
                    'media_size' => $size,
                ]);
                $this->audit($request, 'demo.content.created', $content, ['has_media' => (bool) $path, 'is_active' => $content->is_active]);
                return $content;
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
        return back()->with('success', 'Demo içeriği ayrı Demo havuzuna eklendi. Normal deneyim medyasıyla karıştırılmaz.');
    }

    public function toggleContent(Request $request, DemoContent $content): RedirectResponse
    {
        $isActive = DB::transaction(function () use ($request, $content): bool {
            // Re-read and lock the row to avoid toggling from a stale route-bound model.
            $locked = DemoContent::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();
            $locked->is_active = !$locked->is_active;
            $locked->save();

            // Keep the audit event atomic with the state change.
            $this->audit($request, $locked->is_active ? 'demo.content.activated' : 'demo.content.deactivated', $locked, []);

            return (bool) $locked->is_active;
        });

        return back()->with('success', $isActive ? 'Demo içeriği etkinleştirildi.' : 'Demo içeriği pasife alındı.');
    }

    public function destroyContent(Request $request, DemoContent $content): RedirectResponse
    {
        // A content row may be referenced by a daily assignment while another
        // request is serving the public demo. Never delete bytes from an
        // unexpected disk or path, and check references while holding the row lock.
        $disk = $content->media_disk;
        $path = $content->media_path;
        if ($path !== null && ($disk !== 'local' || !$this->isSafeDemoMediaPath($path))) {
            abort(409, 'Demo medyası özel depolamada veya güvenli bir yolda değil. Dosya korunarak manuel inceleme gerekiyor.');
        }

        try {
            [$disk, $path] = DB::transaction(function () use ($request, $content): array {
                $locked = DemoContent::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();
                abort_if(
                    $locked->assignments()->exists(),
                    409,
                    'Bu içerik demo günlük atama geçmişinde kullanıldığı için silinemez. Önce içeriği pasife alın.'
                );

                $disk = $locked->media_disk;
                $path = $locked->media_path;
                if ($path !== null && ($disk !== 'local' || !$this->isSafeDemoMediaPath($path))) {
                    abort(409, 'Demo medyası özel depolamada veya güvenli bir yolda değil. Dosya korunarak manuel inceleme gerekiyor.');
                }

                $this->audit($request, 'demo.content.deleted', $locked, ['has_media' => (bool) $path]);
                $locked->delete();

                return [$disk, $path];
            });
        } catch (QueryException $exception) {
            // The foreign key remains the final guard if an assignment races
            // with deletion on a database whose lock semantics differ.
            if (DemoDailyAssignment::query()->where('demo_content_id', $content->getKey())->exists()) {
                return back()->withErrors(['content' => 'İçerik eşzamanlı bir demo atamasında kullanıldı; kayıt ve medya korunuyor.']);
            }
            throw $exception;
        }

        if ($disk === 'local' && $path !== null) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', 'Demo içeriği silindi.');
    }

    private function isSafeDemoMediaPath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/')) {
            return false;
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return false;
        }

        return str_starts_with($path, 'orvionart-demo/');
    }

    private function audit(Request $request, string $action, $subject, array $metadata): void
    {
        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => (string) $subject->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
