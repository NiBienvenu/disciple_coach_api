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

class LessonPagesTest extends TestCase
{
    use RefreshDatabase;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $level = Level::query()->create(['slug' => 'pages_test', 'order' => 1, 'part' => 1, 'status' => ContentStatus::Published]);
        $level->translations()->create(['language' => 'fr', 'title' => 'Niveau', 'description' => '']);
        $this->lesson = Lesson::query()->create(['level_id' => $level->id, 'code' => 'PG01', 'slug' => 'pg01', 'type' => LessonType::Main, 'order' => 1, 'status' => ContentStatus::Published]);
        $this->lesson->translations()->create(['language' => 'fr', 'title' => 'Leçon']);

        $admin = User::factory()->create();
        $admin->assignRole(AppRole::Admin->value);
        Sanctum::actingAs($admin);
    }

    private function addPage(array $body): int
    {
        return $this->postJson('/api/v1/admin/content/lessons/'.$this->lesson->id.'/pages', $body)
            ->assertCreated()
            ->json('data.id');
    }

    public function test_pages_are_served_in_order_with_language_fallback(): void
    {
        $intro = $this->addPage([
            'video_url' => 'https://vimeo.com/76979871',
            'translations' => [
                'fr' => ['title' => 'Bienvenue', 'body' => "Texte.\n\n> Car Dieu a tant aimé le monde\n> — Jean 3 : 16"],
                'en' => ['title' => 'Welcome', 'body' => 'Text.'],
            ],
        ]);
        $question = $this->addPage([
            'kind' => 'question',
            'correct_index' => 1,
            'translations' => ['fr' => ['title' => 'Qui ?', 'body' => 'Parce que.', 'choices' => ['A', 'B', 'C']]],
        ]);

        $this->getJson('/api/v1/lessons/'.$this->lesson->id.'?lang=en')
            ->assertOk()
            ->assertJsonCount(2, 'data.pages')
            ->assertJsonPath('data.pages.0.id', $intro)
            ->assertJsonPath('data.pages.0.kind', 'content')
            ->assertJsonPath('data.pages.0.title', 'Welcome')
            ->assertJsonPath('data.pages.0.video_url', 'https://vimeo.com/76979871')
            ->assertJsonPath('data.pages.0.choices', null)
            // No English text yet: French is shown.
            ->assertJsonPath('data.pages.1.id', $question)
            ->assertJsonPath('data.pages.1.title', 'Qui ?')
            ->assertJsonPath('data.pages.1.choices', ['A', 'B', 'C'])
            ->assertJsonPath('data.pages.1.correct_index', 1);
    }

    public function test_admin_reorders_edits_and_deletes_pages(): void
    {
        $a = $this->addPage(['translations' => ['fr' => ['title' => 'A']]]);
        $b = $this->addPage(['translations' => ['fr' => ['title' => 'B']]]);

        $this->putJson('/api/v1/admin/content/lessons/'.$this->lesson->id.'/pages/order', ['ids' => [$b, $a]])
            ->assertOk()
            ->assertJsonPath('data.pages.0.id', $b);

        // Every page must be listed exactly once.
        $this->putJson('/api/v1/admin/content/lessons/'.$this->lesson->id.'/pages/order', ['ids' => [$b]])
            ->assertUnprocessable();

        $this->patchJson('/api/v1/admin/content/lesson-pages/'.$a, ['translations' => ['fr' => ['title' => 'A2']]])
            ->assertOk()
            ->assertJsonPath('data.translations.fr.title', 'A2');

        $this->deleteJson('/api/v1/admin/content/lesson-pages/'.$b)->assertOk();
        $this->getJson('/api/v1/lessons/'.$this->lesson->id)
            ->assertJsonCount(1, 'data.pages')
            ->assertJsonPath('data.pages.0.title', 'A2');
    }

    public function test_question_answer_must_be_one_of_the_choices(): void
    {
        $this->postJson('/api/v1/admin/content/lessons/'.$this->lesson->id.'/pages', [
            'kind' => 'question',
            'correct_index' => 3,
            'translations' => ['fr' => ['title' => 'Q', 'choices' => ['A', 'B']]],
        ])->assertUnprocessable();
    }

    public function test_page_image_upload_and_remove(): void
    {
        $page = $this->addPage(['translations' => ['fr' => ['title' => 'Image']]]);

        $this->post('/api/v1/admin/content/lesson-pages/'.$page.'/image', [
            'image' => UploadedFile::fake()->image('p.jpg', 800, 450),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $this->lesson->pages()->first()->image_url;
        $this->assertStringStartsWith('/uploads/lessons/page-'.$page.'-', $path);
        $this->assertFileExists(public_path(ltrim($path, '/')));

        $this->deleteJson('/api/v1/admin/content/lesson-pages/'.$page.'/image')
            ->assertOk()->assertJsonPath('data.image_url', null);
        $this->assertFileDoesNotExist(public_path(ltrim($path, '/')));
    }
}
