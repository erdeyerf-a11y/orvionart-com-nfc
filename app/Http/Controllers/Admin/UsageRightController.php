<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UsageRight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsageRightController extends Controller
{
    private const RIGHT_TYPES = [
        'standard_free_30_days',
        'account_free_period',
        'first_year_promotion',
        'demo_device_journey',
        'paid_access',
        'custom',
    ];

    private const GRANT_SOURCES = [
        'manual_admin',
        'standard_free_30_days',
        'account_free_period',
        'first_year_promotion',
        'demo_device_journey',
        'paid_access',
        'custom',
    ];

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $rights = $this->filteredQuery($filters)
            ->with(['creator:id,name', 'revoker:id,name'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.usage-rights.index', [
            'rights' => $rights,
            'totalCount' => UsageRight::count(),
            'activeCount' => UsageRight::where('status', 'active')->count(),
            'revokedCount' => UsageRight::where('status', 'revoked')->count(),
            'rightTypes' => self::rightTypes(),
            'grantSources' => self::grantSources(),
        ]);
    }

    /** Export the current filtered view without exposing notes or internal audit metadata. */
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = $this->validatedFilters($request);
        $rights = $this->filteredQuery($filters)
            ->with(['creator:id,name', 'revoker:id,name'])
            ->latest('id')
            ->limit(5001)
            ->get();

        if ($rights->count() > 5000) {
            return redirect()->route('admin.usage-rights.index', $request->query())
                ->withErrors(['export' => 'Dışa aktarım en fazla 5.000 kayıtla sınırlıdır. Lütfen filtreleri daraltıp yeniden deneyin.']);
        }

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'usage_right.export_prepared',
            'subject_type' => UsageRight::class,
            'subject_id' => null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'metadata' => [
                'row_count' => $rights->count(),
                'right_type_filter' => $filters['right_type'] ?? null,
                'status_filter' => $filters['status'] ?? null,
                'search_used' => ($filters['q'] ?? '') !== '',
            ],
            'created_at' => now(),
        ]);

        $rightTypes = self::rightTypes();
        $grantSources = self::grantSources();
        $filename = 'orvionart-usage-rights-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rights, $rightTypes, $grantSources): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            // UTF-8 BOM helps spreadsheet applications recognize Turkish characters.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Kayıt ID', 'Başlık', 'Hak türü', 'Varlık türü', 'Varlık referansı',
                'Kayıt kaynağı', 'Başlangıç', 'Bitiş', 'Kayıt durumu', 'Görüntülenen durum',
                'Oluşturan yönetici', 'Oluşturulma', 'İptal eden yönetici', 'İptal zamanı', 'İptal gerekçesi',
            ], ',', '"', '\\');

            foreach ($rights as $right) {
                $row = [
                    $right->id,
                    $right->label,
                    $rightTypes[$right->right_type] ?? $right->right_type,
                    $right->subject_type,
                    $right->subject_reference,
                    $grantSources[$right->grant_source] ?? $right->grant_source,
                    $right->starts_at?->toIso8601String(),
                    $right->ends_at?->toIso8601String(),
                    $right->status,
                    $right->displayStatus(),
                    $right->creator?->name,
                    $right->created_at?->toIso8601String(),
                    $right->revoker?->name,
                    $right->revoked_at?->toIso8601String(),
                    $right->revocation_reason,
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

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'right_type' => ['nullable', Rule::in(self::RIGHT_TYPES)],
            'status' => ['nullable', Rule::in(['active', 'revoked'])],
        ]);
    }

    private function filteredQuery(array $filters)
    {
        $term = trim((string) ($filters['q'] ?? ''));

        return UsageRight::query()
            ->when($term !== '', function ($query) use ($term): void {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                $query->where(function ($nested) use ($escaped): void {
                    $nested->where('label', 'like', "%{$escaped}%")
                        ->orWhere('subject_type', 'like', "%{$escaped}%")
                        ->orWhere('subject_reference', 'like', "%{$escaped}%")
                        ->orWhere('notes', 'like', "%{$escaped}%");
                });
            })
            ->when(!empty($filters['right_type']), fn ($query) => $query->where('right_type', $filters['right_type']))
            ->when(!empty($filters['status']), fn ($query) => $query->where('status', $filters['status']));
    }

    /** Neutralize spreadsheet formula prefixes before writing untrusted text to CSV. */
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
            'right_type' => ['required', Rule::in(self::RIGHT_TYPES)],
            'label' => ['required', 'string', 'max:180'],
            'subject_type' => ['required', 'string', 'max:100'],
            'subject_reference' => ['nullable', 'string', 'max:190'],
            'grant_source' => ['required', Rule::in(self::GRANT_SOURCES)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ], [
            'ends_at.after_or_equal' => 'Bitiş zamanı başlangıç zamanından önce olamaz.',
        ]);

        $right = DB::transaction(function () use ($request, $data): UsageRight {
            $right = UsageRight::create([
                ...$data,
                'subject_type' => trim($data['subject_type']),
                'subject_reference' => trim((string) ($data['subject_reference'] ?? '')) ?: null,
                'label' => trim($data['label']),
                'created_by' => $request->user()->id,
                'status' => 'active',
            ]);

            $this->audit($request, 'usage_right.created', $right, [
                'right_type' => $right->right_type,
                'subject_type' => $right->subject_type,
                'subject_reference' => $right->subject_reference,
                'grant_source' => $right->grant_source,
                'starts_at' => $right->starts_at?->toIso8601String(),
                'ends_at' => $right->ends_at?->toIso8601String(),
            ]);

            return $right;
        });

        return redirect()->route('admin.usage-rights.index')
            ->with('success', "Kullanım hakkı kaydı #{$right->id} oluşturuldu. Bu kayıt henüz herhangi bir kaynağın erişimini otomatik olarak değiştirmez.");
    }

    public function revoke(Request $request, UsageRight $usageRight): RedirectResponse
    {
        $data = $request->validate([
            'revocation_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $revoked = DB::transaction(function () use ($request, $usageRight, $data): bool {
            // Lock the row so two concurrent requests cannot both record a successful revoke.
            $lockedRight = UsageRight::query()->whereKey($usageRight->getKey())->lockForUpdate()->firstOrFail();
            if (!$lockedRight->isRevocable()) {
                return false;
            }

            $lockedRight->update([
                'status' => 'revoked',
                'revoked_by' => $request->user()->id,
                'revoked_at' => now(),
                'revocation_reason' => trim($data['revocation_reason']),
            ]);

            $this->audit($request, 'usage_right.revoked', $lockedRight, [
                'right_type' => $lockedRight->right_type,
                'revocation_reason' => $lockedRight->revocation_reason,
            ]);

            return true;
        });

        if (!$revoked) {
            return back()->withErrors(['usage_right' => 'Bu kayıt zaten iptal edilmiş.'])->withInput();
        }

        return redirect()->route('admin.usage-rights.index')->with('success', 'Kullanım hakkı kaydı iptal edildi. Bu işlem, henüz bağlanmamış erişim kontrolü üzerinde otomatik etki oluşturmaz.');
    }

    private function audit(Request $request, string $action, UsageRight $right, array $metadata): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => UsageRight::class,
            'subject_id' => (string) $right->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public static function rightTypes(): array
    {
        return [
            'standard_free_30_days' => '30 günlük standart ücretsiz plan',
            'account_free_period' => 'Genel ücretsiz kullanım dönemi',
            'first_year_promotion' => 'İlk yıl promosyonu',
            'demo_device_journey' => 'Demo cihaz yolculuğu (kayıt etiketi)',
            'paid_access' => 'Ücretli erişim hakkı (kayıt etiketi)',
            'custom' => 'Özel / henüz sınıflandırılmamış',
        ];
    }

    public static function grantSources(): array
    {
        return [
            'manual_admin' => 'Yönetici tarafından manuel kayıt',
            'standard_free_30_days' => '30 günlük standart ücretsiz plan',
            'account_free_period' => 'Genel ücretsiz kullanım dönemi',
            'first_year_promotion' => 'İlk yıl promosyonu',
            'demo_device_journey' => 'Demo cihaz yolculuğu',
            'paid_access' => 'Ücretli erişim kaydı',
            'custom' => 'Diğer / özel',
        ];
    }
}
