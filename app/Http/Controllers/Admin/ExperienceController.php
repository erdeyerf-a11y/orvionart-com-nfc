<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Experience;
use App\Models\QrLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExperienceController extends Controller
{
    public function index(Request $request): View
    {
        $experiences = Experience::query()
            ->search($request->query('q'))
            ->when(in_array($request->query('status'), Experience::STATUSES, true), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('admin.experiences.index', [
            'experiences' => $experiences,
            'counts' => [
                'all' => Experience::count(),
                'draft' => Experience::where('status', 'draft')->count(),
                'scheduled' => Experience::where('status', 'scheduled')->count(),
                'published' => Experience::where('status', 'published')->count(),
            ],
            'statuses' => $this->statusLabels(),
        ]);
    }

    public function create(): View
    {
        return view('admin.experiences.form', [
            'experience' => new Experience(['status' => 'draft']),
            'statuses' => $this->statusLabels(),
            'formAction' => route('admin.experiences.store'),
            'formMethod' => 'POST',
            'pageTitle' => 'Yeni deneyim oluştur',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['created_by'] = $request->user()->id;
        $data = $this->applyStatusTimestamps($data);

        $experience = DB::transaction(function () use ($data, $request): Experience {
            $experience = Experience::create($data);
            $this->audit($request, 'experience.created', $experience, ['status' => $experience->status]);
            return $experience;
        });

        return redirect()->route('admin.experiences.edit', $experience)->with('success', $experience->isPubliclyAvailable() ? 'Deneyim kaydedildi ve ziyaretçi sayfası yayında.' : 'Deneyim kaydedildi. Ziyaretçi sayfası yalnızca yayın zamanı geldiğinde ve durum uygunsa açılır.');
    }

    public function edit(Experience $experience): View
    {
        return view('admin.experiences.form', [
            'experience' => $experience,
            'qrLinks' => $experience->qrLinks()->latest()->get(),
            'statuses' => $this->statusLabels(),
            'formAction' => route('admin.experiences.update', $experience),
            'formMethod' => 'PUT',
            'pageTitle' => 'Deneyimi düzenle',
        ]);
    }

    public function update(Request $request, Experience $experience): RedirectResponse
    {
        $data = $this->validatedData($request, $experience);

        DB::transaction(function () use ($data, $request, $experience): void {
            // Lock the experience first. QR operations use the same lock order to
            // avoid a QR-row/experience-row deadlock during publication changes.
            $lockedExperience = Experience::query()->whereKey($experience->getKey())->lockForUpdate()->firstOrFail();
            $before = [
                'title' => $lockedExperience->title,
                'status' => $lockedExperience->status,
                'slug' => $lockedExperience->slug,
            ];
            $dataWithTimestamps = $this->applyStatusTimestamps($data, $lockedExperience);
            $lockedExperience->update($dataWithTimestamps);

            if (in_array($lockedExperience->status, ['draft', 'archived'], true)) {
                $this->deactivateQrLinksForExperience($request, $lockedExperience, 'experience_unavailable');
            }

            $this->audit($request, 'experience.updated', $lockedExperience, [
                'before' => $before,
                'after' => ['title' => $lockedExperience->title, 'status' => $lockedExperience->status, 'slug' => $lockedExperience->slug],
            ]);
        });

        return redirect()->route('admin.experiences.edit', $experience)->with('success', 'Deneyim güncellendi. QR/NFC bağlantıları yayın dışı durumlarda güvenli biçimde devre dışı bırakılır.');
    }

    public function destroy(Request $request, Experience $experience): RedirectResponse
    {
        DB::transaction(function () use ($request, $experience): void {
            // Soft deletion does not trigger database ON DELETE actions. Explicitly
            // disable active QR links while retaining their stable tokens/history.
            $lockedExperience = Experience::query()->whereKey($experience->getKey())->lockForUpdate()->firstOrFail();
            $snapshot = ['title' => $lockedExperience->title, 'slug' => $lockedExperience->slug, 'status' => $lockedExperience->status];
            $this->deactivateQrLinksForExperience($request, $lockedExperience, 'experience_deleted');
            $this->audit($request, 'experience.deleted', $lockedExperience, $snapshot);
            $lockedExperience->delete();
        });

        return redirect()->route('admin.experiences.index')->with('success', 'Deneyim geri alınabilir şekilde arşivlendi (soft delete).');
    }

    private function validatedData(Request $request, ?Experience $experience = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('experiences', 'slug')->ignore($experience?->id)],
            'status' => ['required', Rule::in(Experience::STATUSES)],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:50000'],
            'scheduled_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'ends_at' => ['nullable', 'date'],
        ], [
            'title.required' => 'Deneyim başlığı zorunludur.',
            'slug.regex' => 'Bağlantı kısa adı yalnızca küçük İngilizce harf, rakam ve tire içermelidir.',
            'scheduled_at.required_if' => 'Zamanlanmış durum için tarih ve saat girin.',
        ]);

        if (($data['status'] ?? null) === 'scheduled' && !empty($data['ends_at']) && !empty($data['scheduled_at'])) {
            if (strtotime((string) $data['ends_at']) <= strtotime((string) $data['scheduled_at'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ends_at' => 'Bitiş tarihi, planlanan yayın tarihinden sonra olmalıdır.',
                ]);
            }
        }
        if (($data['status'] ?? null) === 'published' && !empty($data['ends_at'])) {
            $submittedEnd = date('Y-m-d H:i:s', strtotime((string) $data['ends_at']));
            $isNewPublication = !$experience || $experience->status !== 'published';
            if ($isNewPublication && strtotime($submittedEnd) <= now()->timestamp) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ends_at' => 'Yayınlanacak içeriğin bitiş zamanı gelecekte olmalıdır.',
                ]);
            }
        }

        $slugSource = trim((string) ($data['slug'] ?? '')) !== '' ? $data['slug'] : $data['title'];
        $baseSlug = Str::slug($slugSource);
        if ($baseSlug === '') {
            $baseSlug = 'deneyim-' . Str::lower(Str::random(8));
        }

        // The database unique index includes soft-deleted rows, so generated slugs
        // must also avoid every existing row, not just active records.
        $candidate = $baseSlug;
        $suffix = 2;
        while (DB::table('experiences')->where('slug', $candidate)->when($experience, fn ($query) => $query->where('id', '!=', $experience->id))->exists()) {
            $candidate = $baseSlug . '-' . $suffix++;
        }
        $data['slug'] = $candidate;
        $data['summary'] = isset($data['summary']) ? trim($data['summary']) : null;
        $data['body'] = isset($data['body']) ? trim($data['body']) : null;
        return $data;
    }

    private function applyStatusTimestamps(array $data, ?Experience $existing = null): array
    {
        $status = $data['status'];
        $data['scheduled_at'] = $status === 'scheduled' ? ($data['scheduled_at'] ?? $existing?->scheduled_at) : null;
        if (array_key_exists('ends_at', $data)) {
            $data['ends_at'] = isset($data['ends_at']) && trim((string) $data['ends_at']) !== '' ? $data['ends_at'] : null;
        } elseif ($existing) {
            // Backward-compatible updates from older form clients preserve the current expiry.
            $data['ends_at'] = $existing->ends_at;
        } else {
            $data['ends_at'] = null;
        }
        $data['published_at'] = $status === 'published' ? ($existing?->published_at ?? now()) : null;
        $data['archived_at'] = $status === 'archived' ? ($existing?->archived_at ?? now()) : null;
        return $data;
    }

    private function deactivateQrLinksForExperience(Request $request, Experience $experience, string $reason): void
    {
        $links = QrLink::query()
            ->where('experience_id', $experience->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->get();

        foreach ($links as $link) {
            $link->is_active = false;
            $link->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'qr_link.auto_deactivated',
                'subject_type' => QrLink::class,
                'subject_id' => $link->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'metadata' => [
                    'experience_id' => $experience->id,
                    'reason' => $reason,
                    'experience_status' => $experience->status,
                ],
                'created_at' => now(),
            ]);
        }
    }

    private function audit(Request $request, string $action, Experience $experience, array $metadata): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Experience::class,
            'subject_id' => $experience->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function statusLabels(): array
    {
        return [
            'draft' => 'Taslak',
            'scheduled' => 'Zamanlandı',
            'published' => 'Yayınlandı',
            'archived' => 'Arşivlendi',
        ];
    }
}
