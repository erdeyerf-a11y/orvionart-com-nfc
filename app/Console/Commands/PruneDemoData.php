<?php

namespace App\Console\Commands;

use App\Models\DemoEvent;
use App\Models\DemoSession;
use App\Models\DemoSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PruneDemoData extends Command
{
    protected $signature = 'orvionart:prune-demo-data {--dry-run : Report eligible rows without deleting them}';
    protected $description = 'Demo Engine retention policy kapsamında eski oturum ve olay verilerini temizler';

    public function handle(): int
    {
        $settings = DemoSetting::query()->where('setting_key', 'default')->first();
        if (!$settings) {
            $this->info('Demo ayar kaydı bulunamadı; temizlenecek veri yok.');
            return self::SUCCESS;
        }

        $retentionDays = max(1, min(3650, (int) $settings->retention_days));
        $cutoff = Carbon::now()->subDays($retentionDays);

        $oldEvents = DemoEvent::query()->where('created_at', '<', $cutoff)->count();
        $oldSessionsQuery = DemoSession::query()->where(function ($query) use ($cutoff): void {
            $query->where(function ($activeEnded) use ($cutoff): void {
                $activeEnded->whereNull('expired_at')->where('ends_at', '<', $cutoff);
            })->orWhere(function ($explicitlyExpired) use ($cutoff): void {
                $explicitlyExpired->whereNotNull('expired_at')->where('expired_at', '<', $cutoff);
            });
        });
        $oldSessions = (clone $oldSessionsQuery)->count();

        $this->line('Saklama süresi: '.$retentionDays.' gün');
        $this->line('Kesim zamanı: '.$cutoff->toDateTimeString());
        $this->line('Süresi dolmuş/eski oturum adedi: '.$oldSessions);
        $this->line('Eski olay adedi: '.$oldEvents);

        if ($this->option('dry-run')) {
            $this->info('Deneme modu: hiçbir veri silinmedi.');
            return self::SUCCESS;
        }

        $deletedEvents = 0;
        $deletedSessions = 0;
        DB::transaction(function () use ($cutoff, &$deletedEvents, &$deletedSessions): void {
            // Events are pruned independently by event timestamp, including orphaned events.
            $deletedEvents = DemoEvent::query()->where('created_at', '<', $cutoff)->delete();

            // Keep a session until the configured retention period has elapsed after its
            // effective end (natural end or explicit expiry). Child assignments cascade.
            $deletedSessions = DemoSession::query()->where(function ($query) use ($cutoff): void {
                $query->where(function ($activeEnded) use ($cutoff): void {
                    $activeEnded->whereNull('expired_at')->where('ends_at', '<', $cutoff);
                })->orWhere(function ($explicitlyExpired) use ($cutoff): void {
                    $explicitlyExpired->whereNotNull('expired_at')->where('expired_at', '<', $cutoff);
                });
            })->delete();
        });

        $this->info("Temizleme tamamlandı: {$deletedEvents} olay, {$deletedSessions} oturum silindi.");
        return self::SUCCESS;
    }
}
