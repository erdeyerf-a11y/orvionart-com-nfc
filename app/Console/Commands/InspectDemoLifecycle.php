<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectDemoLifecycle extends Command
{
    protected $signature = 'orvionart:inspect-demo-lifecycle {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'Demo Engine oturum, günlük atama ve olay zamanlarının tutarlılığını salt okunur denetler';

    public function handle(): int
    {
        $invalidSessionWindow = DB::table('demo_sessions')
            ->whereColumn('ends_at', '<', 'started_at');
        $lastSeenBeforeStart = DB::table('demo_sessions')
            ->whereNotNull('last_seen_at')
            ->whereColumn('last_seen_at', '<', 'started_at');
        $expiryBeforeStart = DB::table('demo_sessions')
            ->whereNotNull('expired_at')
            ->whereColumn('expired_at', '<', 'started_at');

        $assignmentOutsideSession = DB::table('demo_daily_assignments as assignment')
            ->join('demo_sessions as session', 'session.id', '=', 'assignment.demo_session_id')
            ->where(function ($query): void {
                $query->whereRaw('DATE(assignment.assigned_for) < DATE(session.started_at)')
                    ->orWhereRaw('DATE(assignment.assigned_for) > DATE(session.ends_at)');
            });

        $eventBeforeSessionStart = DB::table('demo_events as event')
            ->join('demo_sessions as session', 'session.id', '=', 'event.demo_session_id')
            ->whereColumn('event.created_at', '<', 'session.started_at');

        $checks = [
            'session_end_before_start' => $this->checkResult($invalidSessionWindow, 'Oturumun bitiş zamanı başlangıç zamanından önce.', 'demo_sessions', 'id'),
            'last_seen_before_start' => $this->checkResult($lastSeenBeforeStart, 'Son görülme zamanı oturum başlangıcından önce.', 'demo_sessions', 'id'),
            'expiry_before_start' => $this->checkResult($expiryBeforeStart, 'Oturumun sona erme zamanı başlangıç zamanından önce.', 'demo_sessions', 'id'),
            'assignment_outside_session_window' => $this->checkResult($assignmentOutsideSession, 'Günlük içerik atama tarihi oturumun tarih aralığı dışında.', 'assignment', 'id'),
            'event_before_session_start' => $this->checkResult($eventBeforeSessionStart, 'Demo olayı ilişkili oturum başlamadan önce kaydedilmiş.', 'event', 'id'),
        ];

        $warnings = [];
        foreach ($checks as $code => $check) {
            if ($check['count'] > 0) {
                $warnings[] = [
                    'code' => $code,
                    'message' => $check['message'],
                    'count' => $check['count'],
                    'sample_ids' => $check['sample_ids'],
                ];
            }
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'checks' => $checks,
            'warnings' => $warnings,
            'limitations' => [
                'Bu komut hiçbir oturum, atama veya olay kaydını değiştirmez ya da silmez.',
                'Bu denetim yalnızca zaman tutarlılığını inceler; ticari kullanım hakkı veya demo erişim politikasını değiştirmez.',
                'Uyarılar kayıtların otomatik düzeltilmesini değil, yetkili incelemeyi gerektirir.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART Demo Engine yaşam döngüsü denetimi (salt okunur)');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            foreach ($checks as $code => $check) {
                $this->line($code.': '.$check['count']);
                if ($check['count'] > 0) {
                    $this->warn($check['message'].' Örnek kayıt ID: '.implode(', ', $check['sample_ids']));
                }
            }
            $this->line('Salt okunur: hiçbir kayıt değiştirilmedi.');
            if ($warnings === []) {
                $this->info('Zaman tutarlılığı uyarısı bulunmadı. Bu rapor son kabul testi değildir.');
            }
        }

        return $warnings === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{count:int,sample_ids:array<int,int>,message:string} */
    private function checkResult($query, string $message, string $tableAlias, string $idColumn): array
    {
        $count = (clone $query)->count();
        $sampleQuery = (clone $query)->select($tableAlias.'.'.$idColumn)->orderBy($tableAlias.'.'.$idColumn)->limit(20);
        $sampleIds = $sampleQuery->pluck($tableAlias.'.'.$idColumn)->map(static fn ($id): int => (int) $id)->all();

        return ['count' => $count, 'sample_ids' => $sampleIds, 'message' => $message];
    }
}
