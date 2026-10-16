# Video processing queue — 0.10.53

## Queue and statuses

The chunked upload pipeline writes the verified source file to private local storage and creates a `MediaAsset` with `processing_status=queued`. The `ProcessUploadedVideo` job is dispatched on the `media` queue after the database transaction commits.

Supported status values:
- `queued`: waiting for a worker.
- `processing`: source verification, FFmpeg conversion and thumbnail generation are running.
- `ready`: the output MP4 was validated and the JPEG thumbnail was stored.
- `failed`: verification/conversion exhausted retries or the job could not be queued.

The source bytes are preserved when verification fails. The public media endpoint and admin video preview do not serve videos until the status is `ready`. An administrator can requeue a failed video from the media library without uploading the source again; this action is audit logged.

## Concurrency and untrusted-input hardening — 0.10.52

The job uses Laravel's `WithoutOverlapping` queue middleware with a per-media-asset key. Duplicate delivery of the same queued job should wait/release rather than running FFmpeg/FFprobe against the same asset concurrently. The job timeout is 300 seconds and the lock expires after 420 seconds, leaving a recovery margin for a worker that dies. The database queue `retry_after` defaults to 360 seconds, greater than the job timeout. The lock requires a cache store that supports Laravel atomic locks; on multi-server deployments, use a shared lock-capable cache store rather than independent per-host file caches.

FFprobe is invoked with `-protocol_whitelist file,pipe`. The input is a server-managed local path, and network protocols are not needed for this validation step. This limits the protocols a crafted upload can request from the worker; it does not replace FFprobe updates, OS-level resource limits, or sandboxing.

## Server setup

1. Install FFmpeg/FFprobe on the staging server and ensure the configured binary is executable by the PHP/worker user.
2. Set `ORVIONART_FFPROBE_BINARY` and `ORVIONART_FFMPEG_BINARY` to executable paths if `ffprobe` or `ffmpeg` are not on the worker PATH.
3. Use `QUEUE_CONNECTION=database` and `DB_QUEUE_RETRY_AFTER=360` (the distributable `.env.example` defaults to both).
4. Apply the migrations, including `2026_10_15_000002_add_video_processing_fields_to_media_assets_table.php` and `2026_10_15_000003_add_video_derivative_paths_to_media_assets_table.php`.
5. Keep Laravel scheduler enabled and run a queue worker, for example:
   `php artisan queue:work database --queue=media,default --tries=3 --timeout=300`
6. Configure the worker under Plesk/supervisor/process manager so it restarts after a crash. Do not assume the HTTP request runs the job.
7. Confirm `jobs` and `failed_jobs` tables exist; monitor worker logs and the failed-jobs list.

## Scope and limitations

As of 0.10.53, the job verifies the source, transcodes to H.264/yuv420p video + AAC audio MP4 when `ORVIONART_VIDEO_TRANSCODING_ENABLED=true`, verifies the output, and creates a JPEG thumbnail. The original upload remains preserved. A video is marked ready only after output validation and thumbnail generation; browser/device playback and supported-codec acceptance still require staging tests. If FFmpeg/FFprobe is missing or the file is malformed, the job retries and then records a safe user-facing failure reason.

## Test boundary

Feature-test source covers retry dispatch, blocking preview before readiness, rejecting retries for already-ready assets, preventing draft-experience thumbnail leaks, and pruning only stale/unreferenced derivative artifacts. Laravel runtime tests, real FFprobe execution, worker supervision, real DB migration, and Plesk tests are not claimed as run in this environment.

## 0.10.53 validation boundary

Static source checks verify that the per-asset overlap middleware, protocol whitelist, transcoding and thumbnail generation sources are present. The Laravel Feature test source asserts middleware presence, but it was not executed because Composer/vendor are unavailable in the build environment. A synthetic 2-second media sample passed a local FFmpeg → FFprobe → JPEG CLI smoke test. Actual Laravel job execution, cross-worker locking, uploaded-file edge cases, Range playback, output cleanup, and queue retry-after behavior must still be verified on staging.
