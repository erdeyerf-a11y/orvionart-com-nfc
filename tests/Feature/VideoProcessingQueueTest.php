<?php

namespace Tests\Feature;

use App\Jobs\ProcessUploadedVideo;
use App\Models\MediaAsset;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class VideoProcessingQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Queue Admin',
            'email' => 'queue-admin@example.test',
            'password' => 'A-very-long-test-password-2026',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    private function video(string $status = 'failed'): MediaAsset
    {
        return MediaAsset::create([
            'experience_id' => null,
            'site_page_id' => null,
            'uploaded_by' => null,
            'title' => 'Queue test video',
            'alt_text' => null,
            'disk' => 'local',
            'path' => 'orvionart-media/2026/10/test.mp4',
            'original_name' => 'test.mp4',
            'mime_type' => 'video/mp4',
            'kind' => 'video',
            'file_size' => 123,
            'processing_status' => $status,
            'processing_error' => $status === 'failed' ? 'FFprobe test error' : null,
            'processing_attempts' => 1,
        ]);
    }


    public function test_video_processing_job_uses_per_asset_overlap_lock_and_transcode_timeout_budget(): void
    {
        $job = new ProcessUploadedVideo(987);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame(300, $job->timeout);
        $this->assertTrue((bool) config('orvionart.video_transcoding_enabled'));
    }

    public function test_failed_video_can_be_requeued_without_reuploading_bytes(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $asset = $this->video();

        $this->actingAs($admin)
            ->post(route('admin.media.video-processing.retry', $asset))
            ->assertRedirect(route('admin.media.index'));

        $asset->refresh();
        $this->assertSame('queued', $asset->processing_status);
        $this->assertNull($asset->processing_error);
        Queue::assertPushed(ProcessUploadedVideo::class, fn (ProcessUploadedVideo $job): bool => $job->mediaAssetId === $asset->id);
    }

    public function test_video_preview_is_not_available_before_processing_is_ready(): void
    {
        $admin = $this->admin();
        $asset = $this->video('queued');

        $this->actingAs($admin)
            ->get(route('admin.media.preview', $asset))
            ->assertStatus(409);
    }


    public function test_video_thumbnail_is_not_available_without_a_generated_thumbnail_path(): void
    {
        $admin = $this->admin();
        $asset = $this->video('ready');

        $this->actingAs($admin)
            ->get(route('admin.media.thumbnail', $asset))
            ->assertNotFound();
    }

    public function test_public_thumbnail_is_not_available_for_an_unpublished_experience(): void
    {
        $experience = Experience::create([
            'title' => 'Draft experience',
            'slug' => 'draft-experience-thumbnail',
            'status' => 'draft',
        ]);
        $asset = $this->video('ready');
        $asset->forceFill([
            'experience_id' => $experience->id,
            'thumbnail_path' => 'orvionart-media/thumbnails/'.$asset->id.'/test.jpg',
        ])->save();

        $this->get(route('media.thumbnail', $asset))->assertNotFound();
    }

    public function test_ready_video_cannot_be_requeued_from_the_failure_action(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $asset = $this->video('ready');

        $this->actingAs($admin)
            ->post(route('admin.media.video-processing.retry', $asset))
            ->assertStatus(409);

        Queue::assertNothingPushed();
    }
}
