<?php

namespace App\Console\Commands;

use App\Models\VideoUploadSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneVideoUploads extends Command
{
    protected $signature = 'orvionart:prune-video-uploads';
    protected $description = 'Süresi dolmuş veya takılı kalmış geçici video yüklemelerini temizler.';

    public function handle(): int
    {
        $deleted = 0;
        VideoUploadSession::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['uploading', 'failed', 'cancelled'])->where('expires_at', '<=', now());
            })
            ->orWhere(function ($query): void {
                $query->where('status', 'assembling')->where('updated_at', '<=', now()->subHour());
            })
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$deleted): void {
                foreach ($sessions as $session) {
                    if ($session->status === 'assembling') {
                        $session->forceFill(['status' => 'failed'])->save();
                    }
                    Storage::disk('local')->deleteDirectory('orvionart-video-uploads/'.$session->uuid);
                    $deleted++;
                }
            });

        $this->info("Geçici video yükleme dizini temizliği tamamlandı. İşlenen oturum: {$deleted}.");
        return self::SUCCESS;
    }
}
