<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneVideoProcessingArtifacts extends Command
{
    protected $signature = 'orvionart:prune-video-processing-artifacts {--hours=24 : Remove only matching temporary or unreferenced derivative artifacts older than this many hours}';
    protected $description = 'Başarısız veya kesilmiş video işlerinden kalan eski geçici türev dosyalarını temizler.';

    public function handle(): int
    {
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT);
        if ($hours === false || $hours < 1 || $hours > 720) {
            $this->error('--hours 1 ile 720 arasında bir tam sayı olmalıdır.');
            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $cutoff = now()->subHours($hours)->getTimestamp();
        $deleted = 0;
        $failed = 0;

        foreach (['orvionart-media/processed', 'orvionart-media/thumbnails'] as $directory) {
            try {
                foreach ($disk->allFiles($directory) as $path) {
                    $basename = basename($path);
                    // Only this application's exact temporary or final derivative naming
                    // convention is eligible; original upload names never match these rules.
                    $isTemporary = preg_match('/^[0-9]+-[a-f0-9]{24}\.tmp\.(?:mp4|jpg)$/D', $basename) === 1;
                    $isFinalDerivative = preg_match('/^[0-9]+-[a-f0-9]{24}\.(?:mp4|jpg)$/D', $basename) === 1;
                    if ((!$isTemporary && !$isFinalDerivative) || !str_starts_with($path, $directory.'/')) {
                        continue;
                    }

                    try {
                        if (!$disk->exists($path) || $disk->lastModified($path) >= $cutoff) {
                            continue;
                        }
                        if ($isFinalDerivative && MediaAsset::query()
                            ->where('processed_path', $path)
                            ->orWhere('thumbnail_path', $path)
                            ->exists()) {
                            continue;
                        }
                        if ($disk->delete($path)) {
                            $deleted++;
                        } else {
                            $failed++;
                        }
                    } catch (Throwable) {
                        $failed++;
                    }
                }
            } catch (Throwable) {
                $failed++;
            }
        }

        $this->info("Geçici video türev temizliği tamamlandı. Silinen: {$deleted}; temizlenemeyen: {$failed}; eşik: {$hours} saat.");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
