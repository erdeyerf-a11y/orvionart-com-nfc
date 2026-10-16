<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessUploadedVideo;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\VideoUploadSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class VideoUploadController extends Controller
{
    public const CHUNK_BYTES = 4 * 1024 * 1024;
    public const MAX_VIDEO_BYTES = 512 * 1024 * 1024;
    private const ALLOWED_MIMES = ['video/mp4', 'video/webm', 'video/ogg'];

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', Rule::in(self::ALLOWED_MIMES)],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_VIDEO_BYTES],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:128'],
            'title' => ['nullable', 'string', 'max:160'],
            'alt_text' => ['nullable', 'string', 'max:250'],
            'experience_id' => ['nullable', 'integer', Rule::exists('experiences', 'id')->whereNull('deleted_at')],
        ]);

        $expectedChunks = (int) ceil(((int) $data['total_size']) / self::CHUNK_BYTES);
        if ((int) $data['total_chunks'] !== $expectedChunks) {
            return response()->json(['message' => 'Parça sayısı dosya boyutuyla uyuşmuyor.'], 422);
        }

        $safeName = basename(str_replace('\\', '/', (string) $data['file_name']));
        $safeName = preg_replace('/[\x00-\x1F\x7F]/u', '', $safeName) ?: 'video-upload';
        $safeName = Str::limit($safeName, 255, '');

        $session = VideoUploadSession::create([
            'uuid' => (string) Str::uuid(),
            'uploaded_by' => $request->user()->id,
            'experience_id' => $data['experience_id'] ?? null,
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'alt_text' => trim((string) ($data['alt_text'] ?? '')) ?: null,
            'original_name' => $safeName,
            'declared_mime' => $data['mime_type'],
            'total_size' => (int) $data['total_size'],
            'chunk_size' => self::CHUNK_BYTES,
            'total_chunks' => $expectedChunks,
            'received_chunks' => [],
            'chunk_hashes' => [],
            'status' => 'uploading',
            'expires_at' => now()->addHours(24),
        ]);

        Storage::disk('local')->makeDirectory($session->chunkDirectory());

        return response()->json([
            'id' => $session->id,
            'status_url' => route('admin.video-uploads.status', $session),
            'chunk_url_template' => route('admin.video-uploads.chunk', ['upload' => $session->id, 'index' => '__INDEX__']),
            'complete_url' => route('admin.video-uploads.complete', $session),
            'cancel_url' => route('admin.video-uploads.cancel', $session),
            'chunk_size' => $session->chunk_size,
            'total_chunks' => $session->total_chunks,
            'received_chunks' => [],
            'chunk_hashes' => [],
            'expires_at' => $session->expires_at->toIso8601String(),
        ], 201);
    }

    public function status(Request $request, VideoUploadSession $upload): JsonResponse
    {
        $this->assertOwner($request, $upload);
        return response()->json($this->statusPayload($upload->fresh()));
    }

    public function chunk(Request $request, VideoUploadSession $upload, int $index): JsonResponse
    {
        $data = $request->validate(['chunk' => ['required', 'file', 'max:5120']]);
        $chunk = $data['chunk'];
        abort_unless($chunk instanceof UploadedFile, 422, 'Parça dosyası okunamadı.');

        $result = DB::transaction(function () use ($request, $upload, $index, $chunk): array {
            $session = VideoUploadSession::query()->whereKey($upload->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($request, $session);
            abort_unless($session->status === 'uploading', 409, 'Yükleme oturumu artık parça kabul etmiyor.');
            abort_if($session->expires_at->isPast(), 410, 'Yükleme oturumunun süresi doldu. Yeni oturum başlatın.');
            abort_unless($index >= 0 && $index < $session->total_chunks, 422, 'Parça numarası geçersiz.');

            $expectedBytes = $index === $session->total_chunks - 1
                ? $session->total_size - ($session->chunk_size * ($session->total_chunks - 1))
                : $session->chunk_size;
            abort_unless((int) $chunk->getSize() === $expectedBytes, 422, 'Parça boyutu beklenen değerle uyuşmuyor.');

            $received = array_values(array_unique(array_map('intval', $session->received_chunks ?? [])));
            $chunkPath = $session->chunkDirectory().'/chunk-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT).'.part';
            $disk = Storage::disk('local');
            $contents = file_get_contents($chunk->getRealPath());
            abort_if($contents === false || strlen($contents) !== $expectedBytes, 422, 'Parça içeriği okunamadı veya boyutu değişti.');
            $chunkHash = hash('sha256', $contents);
            $clientHash = strtolower((string) $request->header('X-Chunk-SHA256', ''));
            abort_unless(
                preg_match('/^[a-f0-9]{64}$/', $clientHash) === 1 && hash_equals($clientHash, $chunkHash),
                422,
                'Parçanın SHA-256 değeri istemci doğrulamasıyla uyuşmuyor.'
            );
            $hashes = $session->chunk_hashes ?? [];

            if (in_array($index, $received, true) && $disk->exists($chunkPath) && (int) $disk->size($chunkPath) === $expectedBytes) {
                $storedHash = $hashes[(string) $index] ?? null;
                abort_unless(is_string($storedHash) && hash_equals($storedHash, $chunkHash), 409, 'Bu parça numarası için farklı içerik gönderildi; yükleme oturumunu yeniden başlatın.');
                return ['duplicate' => true, 'received_chunks' => $received];
            }

            abort_unless($disk->put($chunkPath, $contents), 500, 'Parça güvenli depolamaya yazılamadı.');
            abort_unless((int) $disk->size($chunkPath) === $expectedBytes, 500, 'Kaydedilen parça boyutu doğrulanamadı.');

            $received[] = $index;
            $received = array_values(array_unique($received));
            sort($received);
            $hashes[(string) $index] = $chunkHash;
            ksort($hashes, SORT_NUMERIC);
            $session->forceFill(['received_chunks' => $received, 'chunk_hashes' => $hashes])->save();

            return ['duplicate' => false, 'received_chunks' => $received];
        });

        return response()->json([
            'status' => 'uploading',
            'received_chunks' => $result['received_chunks'],
            'received_count' => count($result['received_chunks']),
            'total_chunks' => $upload->total_chunks,
            'duplicate' => $result['duplicate'],
        ]);
    }

    public function complete(Request $request, VideoUploadSession $upload): JsonResponse
    {
        $session = DB::transaction(function () use ($request, $upload): VideoUploadSession {
            $locked = VideoUploadSession::query()->whereKey($upload->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($request, $locked);
            if ($locked->status === 'completed' && $locked->media_asset_id) {
                return $locked;
            }
            abort_unless($locked->status === 'uploading', 409, 'Yükleme bu durumda tamamlanamaz.');
            abort_if($locked->expires_at->isPast(), 410, 'Yükleme oturumunun süresi doldu.');
            abort_unless($locked->hasReceivedAllChunks(), 409, 'Bütün parçalar yüklenmedi.');
            $locked->forceFill(['status' => 'assembling'])->save();
            return $locked;
        });

        if ($session->status === 'completed' && $session->media_asset_id) {
            return response()->json(['status' => 'completed', 'media_asset_id' => $session->media_asset_id, 'redirect_url' => $this->redirectUrlForExperience($session->experience_id)]);
        }

        $disk = Storage::disk('local');
        $directory = 'orvionart-video-uploads/'.$session->uuid;
        $temporaryPath = $directory.'/assembled.tmp';
        $finalPath = 'orvionart-media/'.now()->format('Y/m').'/'.Str::uuid().$this->extensionFor($session->declared_mime);
        $createdFinalPath = false;
        $assetCommitted = false;

        try {
            $disk->makeDirectory($directory);
            $root = realpath($disk->path(''));
            $temporaryAbsolute = $disk->path($temporaryPath);
            $directoryAbsolute = realpath($disk->path($directory));
            abort_unless($root !== false && $directoryAbsolute !== false && str_starts_with($directoryAbsolute, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR), 500, 'Geçici yükleme dizini güvenli değil.');

            $output = @fopen($temporaryAbsolute, 'wb');
            abort_if($output === false, 500, 'Birleştirme dosyası oluşturulamadı.');
            try {
                for ($index = 0; $index < $session->total_chunks; $index++) {
                    $chunkPath = $session->chunkDirectory().'/chunk-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT).'.part';
                    abort_unless($disk->exists($chunkPath), 409, 'Birleştirme sırasında bir parça bulunamadı.');
                    $chunkAbsolute = realpath($disk->path($chunkPath));
                    abort_unless($chunkAbsolute !== false && str_starts_with($chunkAbsolute, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) && is_file($chunkAbsolute), 422, 'Parça yolu güvenli değil.');
                    $expectedHash = ($session->chunk_hashes ?? [])[(string) $index] ?? null;
                    $actualHash = hash_file('sha256', $chunkAbsolute);
                    abort_unless(is_string($expectedHash) && is_string($actualHash) && hash_equals($expectedHash, $actualHash), 422, 'Birleştirme öncesinde parça bütünlük doğrulaması başarısız oldu.');
                    $input = @fopen($chunkAbsolute, 'rb');
                    abort_if($input === false, 422, 'Parça okunamadı.');
                    try {
                        if (stream_copy_to_stream($input, $output) === false) {
                            throw new \RuntimeException('Video parçaları birleştirilemedi.');
                        }
                    } finally {
                        fclose($input);
                    }
                }
            } finally {
                fclose($output);
            }

            abort_unless((int) $disk->size($temporaryPath) === $session->total_size, 422, 'Birleştirilen dosya boyutu kaynak dosyayla uyuşmuyor.');
            $absolute = realpath($temporaryAbsolute);
            abort_unless($absolute !== false && str_starts_with($absolute, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR), 422, 'Birleştirilen dosya yolu doğrulanamadı.');
            $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
            abort_unless(in_array($detectedMime, self::ALLOWED_MIMES, true), 422, 'Dosyanın gerçek türü desteklenen video biçimlerinden biri değil.');
            abort_unless($detectedMime === $session->declared_mime, 422, 'Dosyanın gerçek türü seçilen dosya türüyle uyuşmuyor.');

            $disk->makeDirectory(dirname($finalPath));
            $finalDirectory = realpath($disk->path(dirname($finalPath)));
            abort_unless($root !== false && $finalDirectory !== false && str_starts_with($finalDirectory, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR), 500, 'Nihai medya dizini güvenli değil.');
            abort_unless($disk->move($temporaryPath, $finalPath), 500, 'Doğrulanmış video medya alanına taşınamadı.');
            $createdFinalPath = true;

            $asset = DB::transaction(function () use ($request, $session, $finalPath, $detectedMime): MediaAsset {
                $locked = VideoUploadSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'assembling' && $locked->media_asset_id === null, 409, 'Yükleme oturumu beklenmeyen durumda.');
                $asset = MediaAsset::create([
                    'experience_id' => $locked->experience_id,
                    'uploaded_by' => $locked->uploaded_by,
                    'title' => $locked->title ?: pathinfo($locked->original_name, PATHINFO_FILENAME),
                    'alt_text' => $locked->alt_text,
                    'disk' => 'local',
                    'path' => $finalPath,
                    'original_name' => $locked->original_name,
                    'mime_type' => $detectedMime,
                    'kind' => 'video',
                    'file_size' => $locked->total_size,
                    'processing_status' => 'queued',
                ]);
                AuditLog::create([
                    'actor_id' => $request->user()->id,
                    'action' => 'media.video_chunk_upload_completed',
                    'subject_type' => MediaAsset::class,
                    'subject_id' => $asset->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500),
                    'metadata' => ['upload_session_id' => $locked->id, 'mime_type' => $detectedMime, 'file_size' => $locked->total_size, 'experience_id' => $locked->experience_id, 'chunks' => $locked->total_chunks],
                    'created_at' => now(),
                ]);
                $locked->forceFill(['status' => 'completed', 'media_asset_id' => $asset->id])->save();
                return $asset;
            });
            $assetCommitted = true;

            try {
                ProcessUploadedVideo::dispatch($asset->id);
            } catch (Throwable $queueException) {
                MediaAsset::query()->whereKey($asset->id)->update([
                    'processing_status' => 'failed',
                    'processing_error' => 'Video işleme işi kuyruğa alınamadı. Kuyruk bağlantısı/worker ayarları kontrol edilmelidir.',
                    'updated_at' => now(),
                ]);
                Log::error('ORVIONART video processing job could not be queued.', [
                    'media_asset_id' => $asset->id,
                    'exception_class' => get_class($queueException),
                ]);
                $asset->refresh();
            }

            // Temporary cleanup is best-effort after the database commit. A cleanup
            // failure must never remove the final file already referenced by MediaAsset.
            try {
                if (!$disk->deleteDirectory($directory)) {
                    Log::warning('Video upload temporary directory cleanup returned false.', [
                        'upload_session_id' => $session->id,
                    ]);
                }
            } catch (Throwable $cleanupException) {
                Log::warning('Video upload temporary directory cleanup failed after completion.', [
                    'upload_session_id' => $session->id,
                    'exception_class' => get_class($cleanupException),
                ]);
            }

            return response()->json(['status' => 'completed', 'media_asset_id' => $asset->id, 'processing_status' => $asset->fresh()->processing_status, 'redirect_url' => $this->redirectUrlForExperience($session->experience_id)]);
        } catch (Throwable $exception) {
            if ($createdFinalPath && !$assetCommitted) {
                $disk->delete($finalPath);
            }
            if (!$assetCommitted) {
                $disk->delete($temporaryPath);
                VideoUploadSession::query()->whereKey($session->id)->where('status', 'assembling')->update(['status' => 'failed', 'updated_at' => now()]);
            }
            throw $exception;
        }
    }

    public function cancel(Request $request, VideoUploadSession $upload): JsonResponse
    {
        $cancelled = DB::transaction(function () use ($request, $upload): bool {
            $session = VideoUploadSession::query()->whereKey($upload->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($request, $session);
            if (in_array($session->status, ['completed', 'cancelled'], true)) {
                return false;
            }
            $session->forceFill(['status' => 'cancelled'])->save();
            return true;
        });

        if ($cancelled) {
            Storage::disk('local')->deleteDirectory('orvionart-video-uploads/'.$upload->uuid);
        }

        return response()->json(['status' => $cancelled ? 'cancelled' : $upload->fresh()->status]);
    }

    private function assertOwner(Request $request, VideoUploadSession $upload): void
    {
        abort_unless((int) $upload->uploaded_by === (int) $request->user()->id, 404);
    }

    private function statusPayload(VideoUploadSession $upload): array
    {
        return [
            'id' => $upload->id,
            'status' => $upload->status,
            'received_chunks' => array_values(array_unique(array_map('intval', $upload->received_chunks ?? []))),
            'chunk_hashes' => $upload->chunk_hashes ?? [],
            'original_name' => $upload->original_name,
            'declared_mime' => $upload->declared_mime,
            'received_count' => count(array_unique($upload->received_chunks ?? [])),
            'total_chunks' => $upload->total_chunks,
            'total_size' => $upload->total_size,
            'media_asset_id' => $upload->media_asset_id,
            'redirect_url' => $this->redirectUrlForExperience($upload->experience_id),
            'expires_at' => $upload->expires_at?->toIso8601String(),
        ];
    }

    private function redirectUrlForExperience(?int $experienceId): string
    {
        if ($experienceId !== null && \App\Models\Experience::query()->whereKey($experienceId)->exists()) {
            return route('admin.experiences.edit', $experienceId);
        }
        return route('admin.media.index');
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'video/mp4' => '.mp4',
            'video/webm' => '.webm',
            'video/ogg' => '.ogv',
            default => throw new \InvalidArgumentException('Desteklenmeyen video türü.'),
        };
    }
}
