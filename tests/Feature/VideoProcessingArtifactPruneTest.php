<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoProcessingArtifactPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_pruner_removes_only_stale_temporary_video_derivatives(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $stale = 'orvionart-media/processed/12/12-aaaaaaaaaaaaaaaaaaaaaaaa.tmp.mp4';
        $fresh = 'orvionart-media/processed/12/12-bbbbbbbbbbbbbbbbbbbbbbbb.tmp.mp4';
        $final = 'orvionart-media/processed/12/12-cccccccccccccccccccccccc.mp4';
        $orphanFinal = 'orvionart-media/processed/12/12-dddddddddddddddddddddddd.mp4';
        $disk->put($stale, 'stale');
        $disk->put($fresh, 'fresh');
        $disk->put($final, 'final');
        $disk->put($orphanFinal, 'orphan');

        $oldTimestamp = now()->subHours(25)->getTimestamp();
        touch($disk->path($stale), $oldTimestamp);
        touch($disk->path($orphanFinal), $oldTimestamp);

        $this->artisan('orvionart:prune-video-processing-artifacts', ['--hours' => 24])
            ->assertExitCode(0);

        $this->assertFalse($disk->exists($stale));
        $this->assertTrue($disk->exists($fresh));
        $this->assertTrue($disk->exists($final));
        $this->assertFalse($disk->exists($orphanFinal));
    }

    public function test_pruner_rejects_unbounded_or_invalid_age(): void
    {
        $this->artisan('orvionart:prune-video-processing-artifacts', ['--hours' => 0])
            ->assertExitCode(1);
    }
}
