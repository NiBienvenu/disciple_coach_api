<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppRole;
use App\Enums\ContentStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonMediaTest extends TestCase
{
    use RefreshDatabase;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $level = Level::query()->create(['slug' => 'track_1', 'order' => 1, 'part' => 1, 'status' => ContentStatus::Published]);
        $level->translations()->create(['language' => 'fr', 'title' => 'Niveau 1', 'description' => '']);
        $this->lesson = Lesson::query()->create(['level_id' => $level->id, 'code' => '1M01', 'slug' => 'l1', 'type' => LessonType::Main, 'order' => 1, 'status' => ContentStatus::Published]);
        $this->lesson->translations()->create(['language' => 'fr', 'title' => 'Leçon 1']);

        $admin = User::factory()->create();
        $admin->assignRole(AppRole::Admin->value);
        Sanctum::actingAs($admin);
    }

    public function test_media_fields_are_null_until_set(): void
    {
        $this->getJson('/api/v1/lessons/'.$this->lesson->id)
            ->assertOk()
            ->assertJsonPath('data.video_url', null)
            ->assertJsonPath('data.image_url', null)
            ->assertJsonPath('data.content.body', null);
    }

    public function test_admin_saves_youtube_link_and_body(): void
    {
        $this->patchJson('/api/v1/admin/content/lessons/'.$this->lesson->id, [
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'translations' => ['fr' => ['title' => 'Leçon 1', 'body' => "Premier paragraphe.\n\nDeuxième."]],
        ])->assertOk()->assertJsonPath('data.video_url', 'https://youtu.be/dQw4w9WgXcQ');

        $this->getJson('/api/v1/lessons/'.$this->lesson->id.'?lang=fr')
            ->assertOk()
            ->assertJsonPath('data.video_url', 'https://youtu.be/dQw4w9WgXcQ')
            ->assertJsonPath('data.content.body', "Premier paragraphe.\n\nDeuxième.");

        // An empty link clears the video.
        $this->patchJson('/api/v1/admin/content/lessons/'.$this->lesson->id, ['video_url' => ''])
            ->assertOk()->assertJsonPath('data.video_url', null);
    }

    public function test_non_youtube_link_is_rejected(): void
    {
        $this->patchJson('/api/v1/admin/content/lessons/'.$this->lesson->id, [
            'video_url' => 'https://vimeo.com/123456',
        ])->assertUnprocessable();
    }

    public function test_admin_uploads_replaces_and_removes_image(): void
    {
        $first = $this->post('/api/v1/admin/content/lessons/'.$this->lesson->id.'/image', [
            'image' => UploadedFile::fake()->image('cover.jpg', 800, 450),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $this->lesson->fresh()->image_url;
        $this->assertStringStartsWith('/uploads/lessons/lesson-'.$this->lesson->id.'-', $path);
        $this->assertFileExists(public_path(ltrim($path, '/')));
        $this->assertSame(url($path), $first->json('data.image_url'));

        $this->post('/api/v1/admin/content/lessons/'.$this->lesson->id.'/image', [
            'image' => UploadedFile::fake()->image('new.png', 400, 400),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertFileDoesNotExist(public_path(ltrim($path, '/')));

        $newPath = $this->lesson->fresh()->image_url;
        $this->deleteJson('/api/v1/admin/content/lessons/'.$this->lesson->id.'/image')
            ->assertOk()->assertJsonPath('data.image_url', null);
        $this->assertFileDoesNotExist(public_path(ltrim($newPath, '/')));
    }

    public function test_upload_rejects_non_images(): void
    {
        $this->post('/api/v1/admin/content/lessons/'.$this->lesson->id.'/image', [
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
