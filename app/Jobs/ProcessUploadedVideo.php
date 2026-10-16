<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessUploadedVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public readonly int $mediaAssetId)
    {
        $this->onQueue('media');
    }

    /**
     * Video transcoding is much more expensive than probing. Keep duplicate jobs
     * for one media record serialized, and let a dead worker's lock expire after
     * the queue timeout plus a recovery margin.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('orvionart-video-processing-'.$this->mediaAssetId))
                ->releaseAfter(15)
                ->expireAfter(420),
        ];
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);
        if (!$asset || $asset->kind !== 'video' || $asset->processing_status === 'ready') {
            return;
        }

        DB::transaction(function (): void {
            $locked = MediaAsset::query()->whereKey($this->mediaAssetId)->lockForUpdate()->first();
            if (!$locked || $locked->kind !== 'video' || $locked->processing_status === 'ready') {
                return;
            }
            $locked->forceFill([
                'processing_status' => 'processing',
                'processing_error' => null,
                'processing_attempts' => (int) $locked->processing_attempts + 1,
            ])->save();
        });

        $disk = Storage::disk('local');
        $temporaryPaths = [];
        $createdPaths = [];
        $published = false;

        try {
            $asset->refresh();
            if ($asset->processing_status === 'ready') {
                return;
            }
            if ($asset->disk !== 'local' || !$this->isSafeManagedMediaPath((string) $asset->path)) {
                throw new RuntimeException('Stored video path failed the managed-media safety check.');
            }

            $sourcePath = $this->resolveManagedFile($disk, (string) $asset->path);
            $sourceMime = (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
            if (!is_string($sourceMime) || !hash_equals((string) $asset->mime_type, $sourceMime)) {
                throw new RuntimeException('Stored video MIME does not match the media record.');
            }

            $sourceMetadata = $this->probeVideo($sourcePath);
            $playbackPath = (string) $asset->path;
            $processedPath = null;

            if ((bool) config('orvionart.video_transcoding_enabled', true)) {
                $directory = 'orvionart-media/processed/'.$asset->id;
                $this->ensureManagedDirectory($disk, $directory);
                $base = (string) $asset->id.'-'.bin2hex(random_bytes(12));
                $temporaryProcessed = $directory.'/'.$base.'.tmp.mp4';
                $processedPath = $directory.'/'.$base.'.mp4';
                $temporaryPaths[] = $temporaryProcessed;

                $this->transcode($sourcePath, $disk->path($temporaryProcessed));
                $processedAbsolute = $this->resolveManagedFile($disk, $temporaryProcessed);
                $processedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($processedAbsolute);
                if (!in_array($processedMime, ['video/mp4', 'application/mp4'], true)) {
                    throw new RuntimeException('FFmpeg output is not a recognized MP4 file.');
                }
                $outputMetadata = $this->probeVideo($processedAbsolute);
                if (($outputMetadata['video']['codec_name'] ?? null) !== 'h264') {
                    throw new RuntimeException('FFmpeg output did not use the required H.264 video codec.');
                }
                if (!$disk->move($temporaryProcessed, $processedPath)) {
                    throw new RuntimeException('Validated MP4 output could not be moved into managed storage.');
                }
                $temporaryPaths = array_values(array_diff($temporaryPaths, [$temporaryProcessed]));
                $createdPaths[] = $processedPath;
                $playbackPath = $processedPath;
                $playbackAbsolute = $this->resolveManagedFile($disk, $processedPath);
                $metadata = $outputMetadata;
            } else {
                $playbackAbsolute = $sourcePath;
                $metadata = $sourceMetadata;
            }

            $thumbnailDirectory = 'orvionart-media/thumbnails/'.$asset->id;
            $this->ensureManagedDirectory($disk, $thumbnailDirectory);
            $thumbnailBase = (string) $asset->id.'-'.bin2hex(random_bytes(12));
            $temporaryThumbnail = $thumbnailDirectory.'/'.$thumbnailBase.'.tmp.jpg';
            $thumbnailPath = $thumbnailDirectory.'/'.$thumbnailBase.'.jpg';
            $temporaryPaths[] = $temporaryThumbnail;
            $this->createThumbnail($playbackAbsolute, $disk->path($temporaryThumbnail));
            $thumbnailAbsolute = $this->resolveManagedFile($disk, $temporaryThumbnail);
            if ((new \finfo(FILEINFO_MIME_TYPE))->file($thumbnailAbsolute) !== 'image/jpeg') {
                throw new RuntimeException('FFmpeg thumbnail output is not a valid JPEG file.');
            }
            if (!$disk->move($temporaryThumbnail, $thumbnailPath)) {
                throw new RuntimeException('Validated thumbnail could not be moved into managed storage.');
            }
            $temporaryPaths = array_values(array_diff($temporaryPaths, [$temporaryThumbnail]));
            $createdPaths[] = $thumbnailPath;

            $updated = MediaAsset::query()->whereKey($asset->id)->where('processing_status', 'processing')->update([
                'processing_status' => 'ready',
                'processing_error' => null,
                'processed_at' => now(),
                'duration_seconds' => $metadata['duration'],
                'video_width' => max(0, (int) $metadata['video']['width']),
                'video_height' => max(0, (int) $metadata['video']['height']),
                'video_codec' => substr((string) $metadata['video']['codec_name'], 0, 100),
                'audio_codec' => isset($metadata['audio']['codec_name']) ? substr((string) $metadata['audio']['codec_name'], 0, 100) : null,
                'processed_path' => $processedPath,
                'thumbnail_path' => $thumbnailPath,
                'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw new RuntimeException('Media state changed before processing output could be published.');
            }
            $published = true;
        } catch (Throwable $exception) {
            foreach (array_merge($temporaryPaths, $published ? [] : $createdPaths) as $path) {
                try {
                    if ($this->isSafeManagedMediaPath($path)) {
                        $disk->delete($path);
                    }
                } catch (Throwable $cleanupException) {
                    Log::warning('ORVIONART video derivative cleanup failed.', [
                        'media_asset_id' => $this->mediaAssetId,
                        'exception_class' => get_class($cleanupException),
                    ]);
                }
            }

            if ($this->attempts() >= $this->tries) {
                MediaAsset::query()->whereKey($this->mediaAssetId)->where('processing_status', '!=', 'ready')->update([
                    'processing_status' => 'failed',
                    'processing_error' => 'Video dönüştürme veya önizleme üretimi başarısız oldu. FFmpeg/FFprobe kurulumu, codec desteği ve sunucu kaynakları kontrol edilmelidir.',
                    'processed_path' => null,
                    'thumbnail_path' => null,
                    'updated_at' => now(),
                ]);
                Log::warning('ORVIONART video processing failed after retries.', [
                    'media_asset_id' => $this->mediaAssetId,
                    'exception_class' => get_class($exception),
                ]);
                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        MediaAsset::query()->whereKey($this->mediaAssetId)->where('processing_status', '!=', 'ready')->update([
            'processing_status' => 'failed',
            'processing_error' => 'Video dönüştürme veya önizleme üretimi başarısız oldu. FFmpeg/FFprobe kurulumu, codec desteği ve sunucu kaynakları kontrol edilmelidir.',
            'processed_path' => null,
            'thumbnail_path' => null,
            'updated_at' => now(),
        ]);
        Log::warning('ORVIONART video processing job exhausted retries.', [
            'media_asset_id' => $this->mediaAssetId,
            'exception_class' => $exception ? get_class($exception) : null,
        ]);
    }

    private function probeVideo(string $absolutePath): array
    {
        $binary = trim((string) config('orvionart.ffprobe_binary', 'ffprobe'));
        if ($binary === '') {
            throw new RuntimeException('FFprobe binary is not configured.');
        }

        $process = new Process([
            $binary,
            '-v', 'error',
            '-protocol_whitelist', 'file,pipe',
            '-show_entries', 'format=duration:stream=codec_type,codec_name,width,height,pix_fmt',
            '-of', 'json',
            $absolutePath,
        ]);
        $process->setTimeout((float) config('orvionart.ffprobe_timeout_seconds', 90));
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('FFprobe could not verify the uploaded video.');
        }

        $probe = json_decode($process->getOutput(), true);
        if (!is_array($probe) || !is_array($probe['streams'] ?? null)) {
            throw new RuntimeException('FFprobe returned invalid metadata.');
        }

        $video = null;
        $audio = null;
        foreach ($probe['streams'] as $stream) {
            if (!is_array($stream)) {
                continue;
            }
            if (($stream['codec_type'] ?? null) === 'video' && $video === null) {
                $video = $stream;
            } elseif (($stream['codec_type'] ?? null) === 'audio' && $audio === null) {
                $audio = $stream;
            }
        }

        $duration = $probe['format']['duration'] ?? null;
        if (!is_array($video) || trim((string) ($video['codec_name'] ?? '')) === ''
            || !is_numeric($duration) || (float) $duration <= 0
            || (int) ($video['width'] ?? 0) < 1 || (int) ($video['height'] ?? 0) < 1) {
            throw new RuntimeException('FFprobe did not return valid video stream metadata.');
        }

        return ['video' => $video, 'audio' => $audio, 'duration' => (float) $duration];
    }

    private function transcode(string $sourceAbsolutePath, string $outputAbsolutePath): void
    {
        $binary = trim((string) config('orvionart.ffmpeg_binary', 'ffmpeg'));
        if ($binary === '') {
            throw new RuntimeException('FFmpeg binary is not configured.');
        }

        $process = new Process([
            $binary,
            '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-protocol_whitelist', 'file,pipe',
            '-i', $sourceAbsolutePath,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-vf', "scale=w='min(1920,iw)':h='min(1920,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart', '-max_muxing_queue_size', '1024',
            '-f', 'mp4', $outputAbsolutePath,
        ]);
        $process->setTimeout((float) config('orvionart.ffmpeg_timeout_seconds', 240));
        $process->run();
        if (!$process->isSuccessful() || !is_file($outputAbsolutePath) || (int) @filesize($outputAbsolutePath) < 1) {
            throw new RuntimeException('FFmpeg could not create a valid playback MP4.');
        }
    }

    private function createThumbnail(string $videoAbsolutePath, string $outputAbsolutePath): void
    {
        $binary = trim((string) config('orvionart.ffmpeg_binary', 'ffmpeg'));
        if ($binary === '') {
            throw new RuntimeException('FFmpeg binary is not configured.');
        }

        $base = [
            $binary, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-protocol_whitelist', 'file,pipe',
        ];
        $process = new Process(array_merge($base, [
            '-ss', '00:00:01.000', '-i', $videoAbsolutePath,
            '-frames:v', '1', '-vf', "scale=w='min(640,iw)':h='min(640,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
            '-q:v', '3', '-f', 'image2', $outputAbsolutePath,
        ]));
        $process->setTimeout(45);
        $process->run();

        // A video shorter than one second may not have a frame at t=1s. Retry
        // from the beginning once, while keeping the same private temporary path.
        if (!$process->isSuccessful() || !is_file($outputAbsolutePath) || (int) @filesize($outputAbsolutePath) < 1) {
            @unlink($outputAbsolutePath);
            $process = new Process(array_merge($base, [
                '-i', $videoAbsolutePath,
                '-frames:v', '1', '-vf', "scale=w='min(640,iw)':h='min(640,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
                '-q:v', '3', '-f', 'image2', $outputAbsolutePath,
            ]));
            $process->setTimeout(45);
            $process->run();
        }

        if (!$process->isSuccessful() || !is_file($outputAbsolutePath) || (int) @filesize($outputAbsolutePath) < 1) {
            throw new RuntimeException('FFmpeg could not create a thumbnail.');
        }
    }

    private function resolveManagedFile($disk, string $path): string
    {
        if (!$this->isSafeManagedMediaPath($path)) {
            throw new RuntimeException('Managed media path is invalid.');
        }
        $root = realpath($disk->path(''));
        $absolute = realpath($disk->path($path));
        if ($root === false || $absolute === false
            || !str_starts_with($absolute, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || !is_file($absolute)) {
            throw new RuntimeException('Managed media file is missing or outside private storage.');
        }
        return $absolute;
    }

    private function ensureManagedDirectory($disk, string $directory): void
    {
        if (!$this->isSafeManagedMediaPath($directory)) {
            throw new RuntimeException('Managed media directory path is invalid.');
        }
        $disk->makeDirectory($directory);
        $root = realpath($disk->path(''));
        $absolute = realpath($disk->path($directory));
        if ($root === false || $absolute === false
            || !str_starts_with($absolute, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || !is_dir($absolute)) {
            throw new RuntimeException('Managed media output directory is not safe.');
        }
    }

    private function isSafeManagedMediaPath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            return false;
        }

        return str_starts_with($path, 'orvionart-media/');
    }
}
