<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Experience;
use App\Models\QrLink;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QrLinkController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'all';

        $query = QrLink::query()->with('experience:id,title,status,published_at,scheduled_at,ends_at');
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('label', 'like', '%' . $search . '%')
                    ->orWhereHas('experience', fn ($experienceQuery) => $experienceQuery->where('title', 'like', '%' . $search . '%'));
            });
        }
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return view('admin.qr-links.index', [
            'links' => $query->latest()->paginate(15)->withQueryString(),
            'experiences' => Experience::query()->whereIn('status', ['published', 'scheduled'])->orderBy('title')->get(['id', 'title', 'status', 'published_at', 'scheduled_at']),
            'filters' => ['q' => $search, 'status' => $status],
        ]);
    }

    /** Export filtered QR/NFC records for inventory and label preparation. */
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $query = QrLink::query()->with('experience:id,title,status,published_at,scheduled_at,ends_at');

        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($builder) use ($escaped): void {
                $builder->where('label', 'like', '%' . $escaped . '%')
                    ->orWhereHas('experience', fn ($experienceQuery) => $experienceQuery->where('title', 'like', '%' . $escaped . '%'));
            });
        }
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $links = $query->latest('id')->limit(5001)->get();
        if ($links->count() > 5000) {
            return redirect()->route('admin.qr-links.index', $request->query())
                ->withErrors(['export' => 'Dışa aktarım en fazla 5.000 QR/NFC kaydıyla sınırlıdır. Filtreleri daraltıp yeniden deneyin.']);
        }

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'qr_link.export_prepared',
            'subject_type' => QrLink::class,
            'subject_id' => null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'metadata' => [
                'row_count' => $links->count(),
                'status_filter' => $status,
                'search_used' => $search !== '',
            ],
            'created_at' => now(),
        ]);

        $filename = 'orvionart-qr-nfc-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($links): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Kayıt ID', 'Etiket adı', 'Deneyim', 'Hedef durumu', 'Bağlantı durumu',
                'Kalıcı QR/NFC adresi', 'Tarama sayısı', 'Son tarama', 'Oluşturulma',
            ], ',', '"', '\\');

            foreach ($links as $link) {
                $row = [
                    $link->id,
                    $link->label,
                    $link->experience?->title ?? 'Deneyim bulunamadı',
                    $link->targetAvailabilityLabel(),
                    $link->is_active ? 'Etkin' : 'Devre dışı',
                    $link->redirectUrl(),
                    $link->scan_count,
                    $link->last_scanned_at?->toIso8601String(),
                    $link->created_at?->toIso8601String(),
                ];
                fputcsv($output, array_map(fn ($value) => self::safeCsvCell($value), $row), ',', '"', '\\');
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Neutralize spreadsheet formula prefixes in untrusted text cells. */
    private static function safeCsvCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');
        if ($cell !== '' && preg_match('/^[\x00-\x20]*[=+\-@]/u', $cell) === 1) {
            return "'" . $cell;
        }
        return $cell;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'experience_id' => ['required', 'integer', 'exists:experiences,id'],
        ], ['experience_id.required' => 'Bağlanacak deneyimi seçin.']);

        $link = DB::transaction(function () use ($request, $data): QrLink {
            // Serialize creation against experience archive/draft transitions.
            $experience = Experience::query()->whereKey($data['experience_id'])->lockForUpdate()->firstOrFail();
            if (!in_array($experience->status, ['published', 'scheduled'], true)) {
                throw ValidationException::withMessages([
                    'experience_id' => 'QR/NFC bağlantısı yalnızca yayınlanan veya yayınlanması planlanan deneyimlere bağlanabilir.',
                ]);
            }

            $link = QrLink::create([
                'experience_id' => $experience->id,
                'created_by' => $request->user()->id,
                'label' => trim($data['label']),
                'is_active' => true,
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'qr_link.created',
                'subject_type' => QrLink::class,
                'subject_id' => $link->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'metadata' => ['experience_id' => $link->experience_id, 'label' => $link->label],
                'created_at' => now(),
            ]);
            return $link;
        });

        return redirect()->route('admin.experiences.edit', $link->experience_id)
            ->with('success', 'QR/NFC bağlantısı oluşturuldu. QR görselini indirebilir veya aşağıdaki kalıcı adresi NFC etiketine yazabilirsiniz.');
    }

    public function preview(QrLink $qrLink): View
    {
        // Validate current lifecycle state before presenting a preview page. The
        // image endpoint repeats this check to avoid stale-page/race conditions.
        $this->currentQrDestination($qrLink);

        return view('admin.qr-links.preview', [
            'link' => $qrLink->fresh(['experience:id,title,status,published_at,scheduled_at,ends_at']),
        ]);
    }

    public function previewImage(QrLink $qrLink)
    {
        return $this->qrImageResponse($qrLink, false);
    }

    public function image(QrLink $qrLink)
    {
        return $this->qrImageResponse($qrLink, true);
    }

    private function qrImageResponse(QrLink $qrLink, bool $download)
    {
        [$url, $linkId] = $this->currentQrDestination($qrLink);

        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 420,
            margin: 16,
            foregroundColor: new Color(15, 23, 42),
            backgroundColor: new Color(255, 255, 255),
        );
        $result = (new SvgWriter())->write($qrCode);

        return response($result->getString(), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="orvionart-qr-' . $linkId . '.svg"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Resolve only a currently active link to an available or scheduled target. */
    private function currentQrDestination(QrLink $qrLink): array
    {
        return DB::transaction(function () use ($qrLink): array {
            // Lock order is experience -> QR, matching lifecycle and public redirect.
            $experienceId = QrLink::query()->whereKey($qrLink->getKey())->value('experience_id');
            abort_if($experienceId === null, 404);

            $experience = Experience::query()->whereKey($experienceId)->lockForUpdate()->firstOrFail();
            $lockedLink = QrLink::query()->whereKey($qrLink->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($lockedLink->is_active, 404);
            abort_if((int) $lockedLink->experience_id !== (int) $experience->id, 404);

            $waitingForScheduledPublication = $experience->status === 'scheduled'
                && $experience->scheduled_at !== null
                && $experience->scheduled_at->isFuture()
                && ($experience->ends_at === null || $experience->ends_at->gt($experience->scheduled_at))
                && ($experience->ends_at === null || $experience->ends_at->isFuture());
            abort_unless($experience->isPubliclyAvailable() || $waitingForScheduledPublication, 404);

            return [$lockedLink->redirectUrl(), (int) $lockedLink->id];
        });
    }

    public function toggle(Request $request, QrLink $qrLink): RedirectResponse
    {
        // Read the parent ID without locking, then use a consistent parent -> QR
        // lock order shared with experience publication and public QR redirects.
        $experienceId = QrLink::query()->whereKey($qrLink->getKey())->value('experience_id');
        abort_if($experienceId === null, 404);

        $result = DB::transaction(function () use ($request, $qrLink, $experienceId): array {
            $experience = Experience::query()->whereKey($experienceId)->lockForUpdate()->firstOrFail();
            $lockedLink = QrLink::query()->whereKey($qrLink->getKey())->lockForUpdate()->firstOrFail();
            abort_if((int) $lockedLink->experience_id !== (int) $experience->id, 409);

            if (!$lockedLink->is_active && !in_array($experience->status, ['published', 'scheduled'], true)) {
                return ['active' => false, 'blocked' => true];
            }

            $lockedLink->is_active = !$lockedLink->is_active;
            $lockedLink->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => $lockedLink->is_active ? 'qr_link.activated' : 'qr_link.deactivated',
                'subject_type' => QrLink::class,
                'subject_id' => $lockedLink->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'metadata' => ['experience_id' => $lockedLink->experience_id],
                'created_at' => now(),
            ]);

            return ['active' => (bool) $lockedLink->is_active, 'blocked' => false];
        });

        if ($result['blocked']) {
            return redirect()->route('admin.qr-links.index')->withErrors([
                'qr_link' => 'Taslak veya arşivlenmiş bir deneyim için QR/NFC bağlantısı etkinleştirilemez. Önce deneyimi yayınlayın veya zamanlayın.',
            ]);
        }

        return redirect()->route('admin.qr-links.index')->with('success', $result['active'] ? 'QR/NFC bağlantısı etkinleştirildi.' : 'QR/NFC bağlantısı devre dışı bırakıldı.');
    }
}
