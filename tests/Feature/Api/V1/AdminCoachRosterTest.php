<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppRole;
use App\Enums\ContentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\LessonType;
use App\Enums\LevelProgressStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Level;
use App\Models\LevelProgress;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCoachRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_sees_each_coach_with_disciples_and_current_level(): void
    {
        $level1 = Level::query()->create(['slug' => 'track_1', 'order' => 1, 'part' => 1, 'status' => ContentStatus::Published]);
        $level1->translations()->create(['language' => 'fr', 'title' => 'Niveau 1', 'description' => '']);
        $level2 = Level::query()->create(['slug' => 'track_2', 'order' => 2, 'part' => 1, 'previous_level_id' => $level1->id, 'status' => ContentStatus::Published]);
        $level2->translations()->create(['language' => 'fr', 'title' => 'Niveau 2', 'description' => '']);
        $lesson = Lesson::query()->create(['level_id' => $level1->id, 'code' => '1M01', 'slug' => 'l1', 'type' => LessonType::Main, 'order' => 1, 'status' => ContentStatus::Published]);
        Lesson::query()->create(['level_id' => $level2->id, 'code' => '2M01', 'slug' => 'l2', 'type' => LessonType::Main, 'order' => 1, 'status' => ContentStatus::Published]);

        $admin = User::factory()->create();
        $admin->assignRole(AppRole::Admin->value);
        $coach = User::factory()->create(['name' => 'Coach A', 'coach_status' => 'approved']);
        $coach->assignRole(AppRole::Coach->value);
        $idleCoach = User::factory()->create(['name' => 'Coach B', 'coach_status' => 'approved']);
        $idleCoach->assignRole(AppRole::Coach->value);
        $disciple = User::factory()->create(['name' => 'Disciple']);
        $disciple->assignRole(AppRole::Disciple->value);
        $ended = User::factory()->create();
        $ended->assignRole(AppRole::Disciple->value);

        CoachDiscipleRelationship::query()->create(['coach_id' => $coach->id, 'disciple_id' => $disciple->id, 'status' => 'active', 'started_at' => now()]);
        CoachDiscipleRelationship::query()->create(['coach_id' => $coach->id, 'disciple_id' => $ended->id, 'status' => 'ended', 'started_at' => now()]);

        LevelProgress::query()->create(['user_id' => $disciple->id, 'level_id' => $level1->id, 'status' => LevelProgressStatus::Approved]);
        LevelProgress::query()->create(['user_id' => $disciple->id, 'level_id' => $level2->id, 'status' => LevelProgressStatus::InProgress]);
        LessonProgress::query()->create(['user_id' => $disciple->id, 'lesson_id' => $lesson->id, 'status' => LessonProgressStatus::Completed]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/coach-roster?lang=fr')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.coach.name', 'Coach A')
            ->assertJsonCount(1, 'data.0.disciples')
            ->assertJsonPath('data.0.disciples.0.name', 'Disciple')
            ->assertJsonPath('data.0.disciples.0.current_level.title', 'Niveau 2')
            ->assertJsonPath('data.0.disciples.0.current_level.status', 'in_progress')
            ->assertJsonPath('data.0.disciples.0.levels_approved', 1)
            ->assertJsonPath('data.0.disciples.0.levels_total', 2)
            ->assertJsonPath('data.0.disciples.0.lessons_completed', 1)
            ->assertJsonPath('data.0.disciples.0.lessons_total', 2)
            ->assertJsonPath('data.0.disciples.0.finished', false)
            ->assertJsonPath('data.1.coach.name', 'Coach B')
            ->assertJsonCount(0, 'data.1.disciples');
    }

    public function test_non_admin_cannot_see_coach_roster(): void
    {
        $coach = User::factory()->create();
        $coach->assignRole(AppRole::Coach->value);
        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/admin/coach-roster')->assertForbidden();
    }
}
