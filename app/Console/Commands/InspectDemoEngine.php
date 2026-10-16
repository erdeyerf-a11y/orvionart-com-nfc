<?php

namespace App\Console\Commands;

use App\Models\DemoContent;
use App\Models\DemoDailyAssignment;
use App\Models\DemoEvent;
use App\Models\DemoSession;
use App\Models\DemoSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectDemoEngine extends Command
{
    protected $signature = 'orvionart:inspect-demo {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'Demo Engine yapılandırmasını ve veri bütünlüğünü salt okunur biçimde denetler';

    public function handle(): int
    {
        $settings = DemoSetting::query()->where('setting_key', 'default')->first();
        $activeContents = DemoContent::query()->where('is_active', true)->count();
        $contentCount = DemoContent::query()->count();
        $now = now();

        $missingMediaIds = [];
        foreach (DemoContent::query()
            ->whereNotNull('media_disk')
            ->whereNotNull('media_path')
            ->get(['id', 'media_disk', 'media_path']) as $content) {
            try {
                if (!Storage::disk((string) $content->media_disk)->exists((string) $content->media_path)) {
                    $missingMediaIds[] = (int) $content->id;
                }
            } catch (Throwable) {
                $missingMediaIds[] = (int) $content->id;
            }
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => $now->toIso8601String(),
            'read_only' => true,
            'engine' => [
                'settings_record_found' => (bool) $settings,
                'active' => (bool) ($settings?->is_active ?? false),
                'journey_days' => $settings?->journey_days,
                'retention_days' => $settings?->retention_days,
            ],
            'counts' => [
                'contents_total' => $contentCount,
                'contents_active' => $activeContents,
                'sessions_total' => DemoSession::query()->count(),
                'sessions_currently_valid' => DemoSession::query()->whereNull('expired_at')->where('ends_at', '>', $now)->count(),
                'sessions_expired_or_ended' => DemoSession::query()->where(function ($query) use ($now): void {
                    $query->whereNotNull('expired_at')->orWhere('ends_at', '<=', $now);
                })->count(),
                'events_last_24_hours' => DemoEvent::query()->where('created_at', '>=', $now->copy()->subDay())->count(),
                'current_assignments_to_inactive_content' => DemoDailyAssignment::query()
                    ->join('demo_contents', 'demo_contents.id', '=', 'demo_daily_assignments.demo_content_id')
                    ->join('demo_sessions', 'demo_sessions.id', '=', 'demo_daily_assignments.demo_session_id')
                    ->whereDate('demo_daily_assignments.assigned_for', $now->toDateString())
                    ->whereNull('demo_sessions.expired_at')
                    ->where('demo_sessions.ends_at', '>', $now)
                    ->where('demo_contents.is_active', false)
                    ->count(),
            ],
            'media' => [
                'checked_assets' => DemoContent::query()->whereNotNull('media_disk')->whereNotNull('media_path')->count(),
                'missing_or_unavailable_asset_ids' => $missingMediaIds,
            ],
            'warnings' => [],
        ];

        if (!$settings) {
            $report['warnings'][] = ['code' => 'demo_settings_missing', 'message' => 'Varsayılan Demo Engine ayar kaydı bulunamadı.'];
        }
        if (($settings?->is_active ?? false) && $activeContents === 0) {
            $report['warnings'][] = ['code' => 'engine_active_without_content', 'message' => 'Demo Engine etkin ancak etkin içerik bulunmuyor.'];
        }
        if ($missingMediaIds !== []) {
            $report['warnings'][] = ['code' => 'demo_media_missing', 'message' => 'Bazı demo medya dosyaları bulunamadı veya depolama diski okunamadı.', 'content_ids' => $missingMediaIds];
        }
        if ($report['counts']['current_assignments_to_inactive_content'] > 0) {
            $report['warnings'][] = ['code' => 'current_assignments_to_inactive_content', 'message' => 'Bugün için geçerli oturumlarda pasif içeriğe bağlı günlük atama var.'];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART Demo Engine salt okunur kontrol raporu');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            $this->line('Rapor zamanı: '.$report['generated_at']);
            $this->line('Demo Engine: '.($report['engine']['settings_record_found'] ? ($report['engine']['active'] ? 'etkin' : 'kapalı') : 'ayar kaydı yok'));
            $this->line('İçerik: '.$contentCount.' toplam / '.$activeContents.' etkin');
            $this->line('Oturum: '.$report['counts']['sessions_total'].' toplam / '.$report['counts']['sessions_currently_valid'].' geçerli / '.$report['counts']['sessions_expired_or_ended'].' sona ermiş');
            $this->line('Son 24 saatteki olay: '.$report['counts']['events_last_24_hours']);
            $this->line('Kontrol edilen medya: '.$report['media']['checked_assets'].'; bulunamayan/okunamayan: '.count($missingMediaIds));
            if ($report['warnings'] === []) {
                $this->info('Uyarı bulunmadı. Bu rapor çalışma zamanı veya güvenlik kabul testi değildir.');
            } else {
                foreach ($report['warnings'] as $warning) {
                    $this->warn(strtoupper($warning['code']).': '.$warning['message']);
                }
            }
            $this->line('Salt okunur: hiçbir kayıt değiştirilmedi.');
        }

        return $report['warnings'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
