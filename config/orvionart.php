<?php

return [
    // Ödeme entegrasyonu bu sürümde uygulanmamıştır; bu anahtar varsayılan olarak kapalıdır.
    'payments_enabled' => (bool) env('ORVIONART_PAYMENTS_ENABLED', false),
    // Search indexing is an explicit production opt-in; staging stays hidden by default.
    'public_indexing_enabled' => (bool) env('ORVIONART_PUBLIC_INDEXING_ENABLED', false),
    // Video workers require FFmpeg/FFprobe binaries on the worker host.
    'ffprobe_binary' => env('ORVIONART_FFPROBE_BINARY', 'ffprobe'),
    'ffmpeg_binary' => env('ORVIONART_FFMPEG_BINARY', 'ffmpeg'),
    'video_transcoding_enabled' => (bool) env('ORVIONART_VIDEO_TRANSCODING_ENABLED', true),
    'ffprobe_timeout_seconds' => (int) env('ORVIONART_FFPROBE_TIMEOUT_SECONDS', 90),
    'ffmpeg_timeout_seconds' => (int) env('ORVIONART_FFMPEG_TIMEOUT_SECONDS', 240),
];
