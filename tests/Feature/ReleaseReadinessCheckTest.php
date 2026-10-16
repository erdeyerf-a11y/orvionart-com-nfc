<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReleaseReadinessCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_check_emits_structured_read_only_report_without_secrets(): void
    {
        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'app.key' => 'test-key-do-not-print',
            'app.url' => 'http://localhost',
            'orvionart.payments_enabled' => false,
        ]);

        $beforeUsers = \DB::table('users')->count();
        $exitCode = Artisan::call('orvionart:release-check', ['--json' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true);

        $this->assertIsArray($report);
        $this->assertSame('ORVIONART', $report['application']);
        $this->assertTrue($report['read_only']);
        $this->assertContains($report['overall'], ['BLOCKED', 'REVIEW_REQUIRED', 'PASS']);
        $this->assertStringContainsString('staging_access_restriction', $output);
        $this->assertStringNotContainsString('test-key-do-not-print', $output);
        $this->assertSame($beforeUsers, \DB::table('users')->count());
        $this->assertContains($exitCode, [0, 1]);
    }

    public function test_release_check_reports_payment_flag_as_pass_when_disabled(): void
    {
        config(['orvionart.payments_enabled' => false]);

        Artisan::call('orvionart:release-check', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);
        $paymentCheck = collect($report['checks'])->firstWhere('code', 'payments_disabled');

        $this->assertSame('pass', $paymentCheck['status']);
    }
    public function test_release_check_blocks_when_payment_feature_flag_is_enabled(): void
    {
        config(['orvionart.payments_enabled' => true]);

        $exitCode = Artisan::call('orvionart:release-check', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);
        $paymentCheck = collect($report['checks'])->firstWhere('code', 'payments_disabled');

        $this->assertSame('fail', $paymentCheck['status']);
        $this->assertSame(1, $exitCode);
    }

    public function test_release_check_reports_upload_queue_and_video_runtime_readiness_without_exposing_paths(): void
    {
        config([
            'queue.default' => 'sync',
            'orvionart.video_transcoding_enabled' => false,
            'orvionart.ffmpeg_binary' => '/private/server/path/ffmpeg',
            'orvionart.ffprobe_binary' => '/private/server/path/ffprobe',
        ]);

        Artisan::call('orvionart:release-check', ['--json' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true);
        $checks = collect($report['checks']);

        $this->assertSame('warn', $checks->firstWhere('code', 'queue_connection')['status']);
        $this->assertContains($checks->firstWhere('code', 'php_upload_max_filesize')['status'], ['pass', 'warn']);
        $this->assertContains($checks->firstWhere('code', 'php_post_max_size')['status'], ['pass', 'warn']);
        $this->assertSame('pass', $checks->firstWhere('code', 'video_processing_binaries')['status']);
        $this->assertStringNotContainsString('/private/server/path', $output);
    }

}
