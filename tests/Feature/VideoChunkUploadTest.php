<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VideoUploadSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoChunkUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'video-admin@example.test'): User
    {
        return User::create([
            'name' => 'Video Admin',
            'email' => $email,
            'password' => 'A-very-long-test-password-2026',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    private function session(User $admin, array $overrides = []): VideoUploadSession
    {
        return VideoUploadSession::create(array_merge([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'uploaded_by' => $admin->id,
            'experience_id' => null,
            'media_asset_id' => null,
            'title' => 'Test video',
            'alt_text' => null,
            'original_name' => 'test.mp4',
            'declared_mime' => 'video/mp4',
            'total_size' => 3,
            'chunk_size' => 4 * 1024 * 1024,
            'total_chunks' => 1,
            'received_chunks' => [],
            'chunk_hashes' => [],
            'status' => 'uploading',
            'expires_at' => now()->addHours(24),
        ], $overrides));
    }

    public function test_super_admin_can_create_a_video_upload_session_with_bounded_chunk_metadata(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.video-uploads.create'), [
            'file_name' => 'atolye.mp4',
            'mime_type' => 'video/mp4',
            'total_size' => 3,
            'total_chunks' => 1,
            'title' => 'Atölye videosu',
        ])->assertCreated()->assertJsonPath('status_url', route('admin.video-uploads.status', VideoUploadSession::firstOrFail()))
            ->assertJsonPath('chunk_size', 4 * 1024 * 1024)
            ->assertJsonPath('total_chunks', 1);

        $session = VideoUploadSession::firstOrFail();
        $this->assertSame($admin->id, $session->uploaded_by);
        $this->assertSame('uploading', $session->status);
        $this->assertSame('atolye.mp4', $session->original_name);
        $this->assertNotEmpty($response->json('chunk_url_template'));
        $this->assertTrue(Storage::disk('local')->directoryExists($session->chunkDirectory()));
    }

    public function test_video_upload_session_rejects_unsupported_mime_and_incorrect_chunk_count(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.video-uploads.create'), [
                'file_name' => 'unsafe.svg', 'mime_type' => 'image/svg+xml', 'total_size' => 3, 'total_chunks' => 1,
            ])->assertUnprocessable();

        $this->actingAs($this->admin('second-video-admin@example.test'))
            ->postJson(route('admin.video-uploads.create'), [
                'file_name' => 'atolye.mp4', 'mime_type' => 'video/mp4', 'total_size' => 4 * 1024 * 1024 + 1, 'total_chunks' => 1,
            ])->assertUnprocessable();
    }

    public function test_chunk_upload_records_received_part_and_rejects_wrong_chunk_size(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $session = $this->session($admin);

        $this->actingAs($admin)->post(route('admin.video-uploads.chunk', ['upload' => $session->id, 'index' => 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('chunk.part', 'abc'),
        ], ['X-Chunk-SHA256' => hash('sha256', 'abc')])->assertOk()->assertJsonPath('received_chunks.0', 0)->assertJsonPath('received_count', 1);

        $session->refresh();
        $this->assertSame([0], $session->received_chunks);
        $this->assertSame(hash('sha256', 'abc'), $session->chunk_hashes['0']);
        Storage::disk('local')->assertExists($session->chunkDirectory().'/chunk-0000.part');

        $this->actingAs($admin)->post(route('admin.video-uploads.chunk', ['upload' => $session->id, 'index' => 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('chunk.part', 'xyz'),
        ], ['X-Chunk-SHA256' => hash('sha256', 'xyz')])->assertStatus(409);

        $other = $this->session($admin, ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'total_size' => 4]);
        $this->actingAs($admin)->post(route('admin.video-uploads.chunk', ['upload' => $other->id, 'index' => 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('chunk.part', 'abc'),
        ], ['X-Chunk-SHA256' => hash('sha256', 'abc')])->assertUnprocessable();
    }

    public function test_chunk_upload_requires_matching_client_sha256(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $session = $this->session($admin);

        $this->actingAs($admin)->post(route('admin.video-uploads.chunk', ['upload' => $session->id, 'index' => 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('chunk.part', 'abc'),
        ], ['X-Chunk-SHA256' => hash('sha256', 'different')])->assertUnprocessable();

        $this->assertSame([], $session->fresh()->received_chunks);
        Storage::disk('local')->assertMissing($session->chunkDirectory().'/chunk-0000.part');
    }

    public function test_video_completion_rejects_content_that_does_not_match_a_video_mime(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $session = $this->session($admin);
        Storage::disk('local')->put($session->chunkDirectory().'/chunk-0000.part', 'abc');
        $session->forceFill(['received_chunks' => [0]])->save();

        $this->actingAs($admin)
            ->postJson(route('admin.video-uploads.complete', $session))
            ->assertUnprocessable();

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertSame(0, MediaAsset::query()->count());
    }

    public function test_video_upload_session_is_private_to_its_creator(): void
    {
        $owner = $this->admin();
        $other = $this->admin('other-video-admin@example.test');
        $session = $this->session($owner);

        $this->actingAs($other)
            ->getJson(route('admin.video-uploads.status', $session))
            ->assertNotFound();
    }

    public function test_video_upload_cannot_be_completed_before_all_chunks_arrive(): void
    {
        $admin = $this->admin();
        $session = $this->session($admin, ['total_size' => 4 * 1024 * 1024 + 1, 'total_chunks' => 2]);

        $this->actingAs($admin)
            ->postJson(route('admin.video-uploads.complete', $session))
            ->assertStatus(409);

        $this->assertSame('uploading', $session->fresh()->status);
    }
}
