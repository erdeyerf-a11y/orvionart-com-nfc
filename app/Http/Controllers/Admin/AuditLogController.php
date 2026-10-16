<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /** Display-only audit trail. No mutation or export endpoints are provided. */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (!empty($filters['from']) && !empty($filters['to']) && $filters['to'] < $filters['from']) {
            throw ValidationException::withMessages([
                'to' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.',
            ]);
        }

        $query = AuditLog::query()->with('actor:id,name,email');
        $term = trim((string) ($filters['q'] ?? ''));

        if ($term !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $query->where(function (Builder $nested) use ($escaped): void {
                $nested->where('action', 'like', "%{$escaped}%")
                    ->orWhere('subject_type', 'like', "%{$escaped}%")
                    ->orWhere('subject_id', 'like', "%{$escaped}%")
                    ->orWhereHas('actor', function (Builder $actor) use ($escaped): void {
                        $actor->where('name', 'like', "%{$escaped}%")
                            ->orWhere('email', 'like', "%{$escaped}%");
                    });
            });
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to'] . ' 23:59:59');
        }

        $logs = $query->latest('created_at')->latest('id')->paginate(30)->withQueryString();
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->limit(200)->pluck('action');

        // Redact secret-like keys before passing arbitrary JSON metadata to the view.
        $logs->getCollection()->transform(function (AuditLog $log): AuditLog {
            $log->setAttribute('safe_metadata', $this->redactMetadata($log->metadata ?? []));
            return $log;
        });

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => $actions,
            'totalCount' => AuditLog::count(),
            'filters' => $filters,
        ]);
    }

    private function redactMetadata(array $metadata): array
    {
        $redacted = [];
        foreach ($metadata as $key => $value) {
            $name = (string) $key;
            if (preg_match('/password|secret|token|api[_-]?key|authorization|cookie|credential/i', $name)) {
                $redacted[$name] = '[GİZLENDİ]';
                continue;
            }
            if (is_array($value)) {
                $redacted[$name] = $this->redactMetadata($value);
            } elseif (is_scalar($value) || $value === null) {
                $redacted[$name] = $value;
            } else {
                $redacted[$name] = '[DESTEKLENMEYEN DEĞER]';
            }
        }
        return $redacted;
    }
}
