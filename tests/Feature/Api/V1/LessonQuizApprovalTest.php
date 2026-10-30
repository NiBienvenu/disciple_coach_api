<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppRole;
use App\Enums\ContentStatus;
use App\Enums\LessonType;
use App\Models\CoachDiscipleRelationship;
use App\Models\Lesson;
use App\Models\LessonPage;
use App\Models\Level;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonQuizApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $disciple;

    private User $coach;

    private Lesson $first;

    private Lesson $second;

    private LessonPage $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->disciple = User::factory()->create();
        $this->disciple->assignRole(AppRole::Disciple->value);
        $this->coach = User::factory()->create(['coach_status' => 'approved', 'is_active' => true]);
        $this->coach->assignRole(AppRole::Coach->value);
        CoachDiscipleRelationship::query()->create([
            'coach_id' => $this->coach->id,
            'disciple_id' => $this->disciple->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // Order 0: before the seeded levels, so it is open to everyone.
        $level = Level::query()->create(['slug' => 'approval_test', 'order' => 0, 'part' => 1, 'status' => ContentStatus::Published]);
        $level->translations()->create(['language' => 'fr', 'title' => 'Niveau', 'description' => '']);
        $this->first = $this->lesson($level, 'AP01', 1);
        $this->second = $this->lesson($level, 'AP02', 2);

        $this->question = $this->first->pages()->create(['order' => 1, 'kind' => LessonPage::KIND_QUESTION, 'correct_index' => 1]);
        $this->question->translations()->create(['language' => 'fr', 'title' => 'Qui ?', 'choices' => ['A', 'B', 'C']]);
    }

    private function lesson(Level $level, string $code, int $order): Lesson
    {
        $lesson = Lesson::query()->create(['level_id' => $level->id, 'code' => $code, 'slug' => strtolower($code), 'type' => LessonType::Main, 'order' => $order, 'status' => ContentStatus::Published]);
        $lesson->translations()->create(['language' => 'fr', 'title' => $code]);

        return $lesson;
    }

    private function send(int $choice)
    {
        Sanctum::actingAs($this->disciple);

        return $this->postJson('/api/v1/lessons/'.$this->first->id.'/quiz-attempts', [
            'answers' => [['page_id' => $this->question->id, 'selected_index' => $choice]],
        ]);
    }

    private function decide(int $attemptId, string $decision, ?string $feedback = null)
    {
        Sanctum::actingAs($this->coach);

        return $this->postJson('/api/v1/lesson-quiz-attempts/'.$attemptId.'/decide', array_filter([
            'decision' => $decision,
            'feedback' => $feedback,
        ]));
    }

    private function completeSecond()
    {
        Sanctum::actingAs($this->disciple);

        return $this->putJson('/api/v1/me/progress/lessons/'.$this->second->id, ['status' => 'completed']);
    }

    public function test_lesson_with_questions_needs_answers_to_complete(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->putJson('/api/v1/me/progress/lessons/'.$this->first->id, ['status' => 'completed'])
            ->assertUnprocessable();
    }

    public function test_next_lesson_opens_only_after_coach_approval(): void
    {
        $id = $this->send(0)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.answers.0.is_correct', false)
            ->assertJsonPath('data.answers.0.prompt', 'Qui ?')
            ->json('data.id');

        $this->completeSecond()->assertForbidden();

        $this->decide($id, 'approve')->assertOk()->assertJsonPath('data.status', 'approved');
        $this->completeSecond()->assertOk();
    }

    public function test_coach_reset_makes_the_disciple_answer_again(): void
    {
        $id = $this->send(0)->json('data.id');

        $this->decide($id, 'reset', 'Relisez la page 3.')
            ->assertOk()
            ->assertJsonPath('data.status', 'reset')
            ->assertJsonPath('data.feedback', 'Relisez la page 3.');

        Sanctum::actingAs($this->disciple);
        $this->getJson('/api/v1/me/lesson-quiz-attempts')->assertOk()->assertJsonPath('data.0.status', 'reset');
        $this->completeSecond()->assertForbidden();

        $this->send(1)->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.feedback', null);
    }

    public function test_only_the_assigned_coach_decides(): void
    {
        $id = $this->send(1)->json('data.id');
        $stranger = User::factory()->create(['coach_status' => 'approved', 'is_active' => true]);
        $stranger->assignRole(AppRole::Coach->value);
        Sanctum::actingAs($stranger);

        $this->postJson('/api/v1/lesson-quiz-attempts/'.$id.'/decide', ['decision' => 'approve'])->assertForbidden();
        $this->getJson('/api/v1/coach/disciples/'.$this->disciple->id.'/lesson-quiz-attempts')->assertForbidden();

        Sanctum::actingAs($this->coach);
        $this->getJson('/api/v1/coach/disciples/'.$this->disciple->id.'/lesson-quiz-attempts')
            ->assertOk()
            ->assertJsonPath('data.0.answers.0.choices', ['A', 'B', 'C']);
    }
}
