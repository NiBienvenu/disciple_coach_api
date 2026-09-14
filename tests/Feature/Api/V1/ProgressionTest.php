<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppRole;
use App\Enums\ContentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\LessonType;
use App\Enums\LevelProgressStatus;
use App\Enums\MentorReviewStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgressionTest extends TestCase
{
    use RefreshDatabase;

    private Level $level1;

    private Level $level2;

    private Lesson $lesson1a;

    private Lesson $lesson1b;

    private Lesson $lesson2a;

    private Quiz $quiz1;

    private QuizQuestion $question1;

    private QuizQuestion $question2;

    private User $disciple;

    private User $coach;

    private User $otherCoach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seedCurriculum();
        $this->seedUsers();
    }

    public function test_disciple_cannot_submit_quiz_for_locked_level(): void
    {
        Sanctum::actingAs($this->disciple);

        // Bootstrap level progress (level 2 stays locked without prior approval).
        $this->getJson('/api/v1/me/level-progress')->assertOk();

        $quiz2 = Quiz::query()->where('level_id', $this->level2->id)->firstOrFail();
        $answers = $quiz2->questions->map(fn (QuizQuestion $q) => [
            'question_id' => $q->id,
            'selected_index' => $q->correct_index,
        ])->values()->all();

        $this->postJson('/api/v1/levels/'.$this->level2->id.'/quiz-attempts', [
            'answers' => $answers,
        ])->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_completing_all_lessons_allows_quiz(): void
    {
        Sanctum::actingAs($this->disciple);

        $this->putJson('/api/v1/me/progress/lessons/'.$this->lesson1a->id)
            ->assertOk();

        $this->assertFalse(
            app(\App\Services\Progress\LevelProgressionService::class)
                ->canTakeQuiz($this->disciple, $this->level1)
        );

        $this->putJson('/api/v1/me/progress/lessons/'.$this->lesson1b->id)
            ->assertOk();

        $this->assertTrue(
            app(\App\Services\Progress\LevelProgressionService::class)
                ->canTakeQuiz($this->disciple, $this->level1)
        );

        $this->assertDatabaseHas('level_progress', [
            'user_id' => $this->disciple->id,
            'level_id' => $this->level1->id,
            'status' => LevelProgressStatus::QuizPending->value,
        ]);
    }

    public function test_quiz_submission_creates_attempt_with_server_score(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->completeAllLessons($this->level1);

        $response = $this->postJson('/api/v1/levels/'.$this->level1->id.'/quiz-attempts', [
            'answers' => [
                ['question_id' => $this->question1->id, 'selected_index' => 1], // correct
                ['question_id' => $this->question2->id, 'selected_index' => 0], // wrong
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.score', 0.5)
            ->assertJsonPath('data.mentor_status', MentorReviewStatus::Pending->value)
            ->assertJsonPath('data.advancement_approved', false);

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $this->disciple->id,
            'level_id' => $this->level1->id,
            'score' => 0.5,
        ]);

        $this->assertDatabaseCount('quiz_answers', 2);
    }

    public function test_coach_approve_unlocks_next_level(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->completeAllLessons($this->level1);
        $attempt = $this->submitLevel1Quiz();

        Sanctum::actingAs($this->coach);
        $this->postJson('/api/v1/quiz-attempts/'.$attempt->id.'/decide', [
            'decision' => 'approve',
            'feedback' => 'Great work',
        ])->assertOk()
            ->assertJsonPath('data.mentor_status', MentorReviewStatus::Approved->value)
            ->assertJsonPath('data.advancement_approved', true);

        $this->assertDatabaseHas('level_progress', [
            'user_id' => $this->disciple->id,
            'level_id' => $this->level1->id,
            'status' => LevelProgressStatus::Approved->value,
        ]);

        $this->assertDatabaseHas('level_progress', [
            'user_id' => $this->disciple->id,
            'level_id' => $this->level2->id,
            'status' => LevelProgressStatus::Available->value,
        ]);
    }

    public function test_coach_reject_keeps_next_level_locked(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->completeAllLessons($this->level1);
        $attempt = $this->submitLevel1Quiz();

        Sanctum::actingAs($this->coach);
        $this->postJson('/api/v1/quiz-attempts/'.$attempt->id.'/decide', [
            'decision' => 'reject',
            'feedback' => 'Please retake',
        ])->assertOk()
            ->assertJsonPath('data.mentor_status', MentorReviewStatus::ChangesRequested->value)
            ->assertJsonPath('data.advancement_approved', false);

        $this->assertDatabaseHas('level_progress', [
            'user_id' => $this->disciple->id,
            'level_id' => $this->level1->id,
            'status' => LevelProgressStatus::NeedsReview->value,
        ]);

        $this->assertDatabaseHas('level_progress', [
            'user_id' => $this->disciple->id,
            'level_id' => $this->level2->id,
            'status' => LevelProgressStatus::Locked->value,
        ]);
    }

    public function test_coach_cannot_review_other_coachs_disciple(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->completeAllLessons($this->level1);
        $attempt = $this->submitLevel1Quiz();

        Sanctum::actingAs($this->otherCoach);
        $this->postJson('/api/v1/quiz-attempts/'.$attempt->id.'/decide', [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->getJson('/api/v1/coach/disciples/'.$this->disciple->id.'/quiz-attempts')
            ->assertForbidden();

        $this->getJson('/api/v1/coach/disciples/'.$this->disciple->id.'/progress')
            ->assertForbidden();
    }

    public function test_disciple_cannot_approve_own_level(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->completeAllLessons($this->level1);
        $attempt = $this->submitLevel1Quiz();

        $this->postJson('/api/v1/quiz-attempts/'.$attempt->id.'/decide', [
            'decision' => 'approve',
        ])->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_duplicate_lesson_complete_is_idempotent(): void
    {
        Sanctum::actingAs($this->disciple);

        $first = $this->putJson('/api/v1/me/progress/lessons/'.$this->lesson1a->id)
            ->assertOk()
            ->json('data');

        $second = $this->putJson('/api/v1/me/progress/lessons/'.$this->lesson1a->id)
            ->assertOk()
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(LessonProgressStatus::Completed->value, $second['status']);
        $this->assertDatabaseCount('lesson_progress', 1);
        $this->assertSame($first['completed_at'], $second['completed_at']);
    }

    public function test_retake_does_not_clear_advancement_approved(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->completeAllLessons($this->level1);
        $attempt = $this->submitLevel1Quiz();

        Sanctum::actingAs($this->coach);
        $this->postJson('/api/v1/quiz-attempts/'.$attempt->id.'/decide', [
            'decision' => 'approve',
        ])->assertOk();

        $approvedAt = QuizAttempt::query()->findOrFail($attempt->id)->advancement_approved_at;

        Sanctum::actingAs($this->disciple);
        $this->postJson('/api/v1/levels/'.$this->level1->id.'/quiz-attempts', [
            'answers' => [
                ['question_id' => $this->question1->id, 'selected_index' => 0],
                ['question_id' => $this->question2->id, 'selected_index' => 0],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.mentor_status', MentorReviewStatus::Pending->value)
            ->assertJsonPath('data.advancement_approved', true)
            ->assertJsonPath('data.score', 0);

        $fresh = QuizAttempt::query()->findOrFail($attempt->id);
        $this->assertTrue($fresh->advancement_approved);
        $this->assertTrue(
            $fresh->advancement_approved_at?->equalTo($approvedAt) ?? false
        );
        $this->assertDatabaseCount('quiz_attempts', 1);

        $this->assertDatabaseHas('level_progress', [
            'user_id' => $this->disciple->id,
            'level_id' => $this->level2->id,
            'status' => LevelProgressStatus::Available->value,
        ]);
    }

    public function test_level_one_is_available_after_bootstrap(): void
    {
        Sanctum::actingAs($this->disciple);

        $this->getJson('/api/v1/me/level-progress')
            ->assertOk()
            ->assertJsonPath('data.0.level_id', $this->level1->id)
            ->assertJsonPath('data.0.status', LevelProgressStatus::Available->value)
            ->assertJsonPath('data.1.status', LevelProgressStatus::Locked->value);
    }

    public function test_assigned_coach_can_view_disciple_progress_and_attempts(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->completeAllLessons($this->level1);
        $this->submitLevel1Quiz();

        Sanctum::actingAs($this->coach);
        $this->getJson('/api/v1/coach/disciples/'.$this->disciple->id.'/progress')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/coach/disciples/'.$this->disciple->id.'/quiz-attempts')
            ->assertOk()
            ->assertJsonPath('data.0.answers.0.correct_index', 1);
    }

    private function submitLevel1Quiz(): QuizAttempt
    {
        $this->postJson('/api/v1/levels/'.$this->level1->id.'/quiz-attempts', [
            'answers' => $this->correctAnswersForQuiz($this->quiz1),
        ])->assertCreated();

        return QuizAttempt::query()
            ->where('user_id', $this->disciple->id)
            ->where('level_id', $this->level1->id)
            ->firstOrFail();
    }

    private function completeAllLessons(Level $level): void
    {
        $lessons = Lesson::query()->where('level_id', $level->id)->get();
        foreach ($lessons as $lesson) {
            $this->putJson('/api/v1/me/progress/lessons/'.$lesson->id)->assertOk();
        }
    }

    /**
     * @return list<array{question_id: int, selected_index: int}>
     */
    private function correctAnswersForQuiz(Quiz $quiz): array
    {
        return $quiz->questions()->get()->map(fn (QuizQuestion $q) => [
            'question_id' => $q->id,
            'selected_index' => $q->correct_index,
        ])->values()->all();
    }

    private function seedUsers(): void
    {
        $this->disciple = User::factory()->create(['email' => 'disciple@example.com']);
        $this->disciple->assignRole(AppRole::Disciple->value);

        $this->coach = User::factory()->create(['email' => 'coach@example.com']);
        $this->coach->assignRole(AppRole::Coach->value);

        $this->otherCoach = User::factory()->create(['email' => 'other-coach@example.com']);
        $this->otherCoach->assignRole(AppRole::Coach->value);

        CoachDiscipleRelationship::query()->create([
            'coach_id' => $this->coach->id,
            'disciple_id' => $this->disciple->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    private function seedCurriculum(): void
    {
        $this->level1 = Level::query()->create([
            'slug' => 'track_1',
            'order' => 1,
            'previous_level_id' => null,
            'status' => ContentStatus::Published,
        ]);
        $this->level1->translations()->create([
            'language' => 'fr',
            'title' => 'Niveau 1',
            'description' => 'Fondation',
        ]);

        $this->level2 = Level::query()->create([
            'slug' => 'track_2',
            'order' => 2,
            'previous_level_id' => $this->level1->id,
            'status' => ContentStatus::Published,
        ]);
        $this->level2->translations()->create([
            'language' => 'fr',
            'title' => 'Niveau 2',
            'description' => 'Suite',
        ]);

        $this->lesson1a = Lesson::query()->create([
            'level_id' => $this->level1->id,
            'code' => '1M01',
            'slug' => 'track_1_lesson_01',
            'type' => LessonType::Main,
            'order' => 1,
            'status' => ContentStatus::Published,
        ]);
        $this->lesson1b = Lesson::query()->create([
            'level_id' => $this->level1->id,
            'code' => '1M02',
            'slug' => 'track_1_lesson_02',
            'type' => LessonType::Main,
            'order' => 2,
            'status' => ContentStatus::Published,
        ]);
        $this->lesson2a = Lesson::query()->create([
            'level_id' => $this->level2->id,
            'code' => '2M01',
            'slug' => 'track_2_lesson_01',
            'type' => LessonType::Main,
            'order' => 1,
            'status' => ContentStatus::Published,
        ]);

        $this->quiz1 = Quiz::query()->create([
            'level_id' => $this->level1->id,
            'status' => ContentStatus::Published,
        ]);
        $this->question1 = $this->quiz1->questions()->create([
            'order' => 1,
            'correct_index' => 1,
        ]);
        $this->question1->translations()->create([
            'language' => 'fr',
            'prompt' => 'Q1?',
            'choices' => ['A', 'B'],
        ]);
        $this->question2 = $this->quiz1->questions()->create([
            'order' => 2,
            'correct_index' => 1,
        ]);
        $this->question2->translations()->create([
            'language' => 'fr',
            'prompt' => 'Q2?',
            'choices' => ['A', 'B'],
        ]);

        $quiz2 = Quiz::query()->create([
            'level_id' => $this->level2->id,
            'status' => ContentStatus::Published,
        ]);
        $q = $quiz2->questions()->create([
            'order' => 1,
            'correct_index' => 0,
        ]);
        $q->translations()->create([
            'language' => 'fr',
            'prompt' => 'L2 Q1?',
            'choices' => ['Yes', 'No'],
        ]);
    }
}
