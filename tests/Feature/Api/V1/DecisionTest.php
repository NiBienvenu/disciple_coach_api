<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppRole;
use App\Enums\ContentStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\LessonPage;
use App\Models\Level;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DecisionTest extends TestCase
{
    use RefreshDatabase;

    private LessonPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $level = Level::query()->create(['slug' => 'decision_test', 'order' => 0, 'part' => 1, 'status' => ContentStatus::Published]);
        $lesson = Lesson::query()->create(['level_id' => $level->id, 'code' => 'DC01', 'slug' => 'dc01', 'type' => LessonType::Main, 'order' => 1, 'status' => ContentStatus::Published]);
        $lesson->translations()->create(['language' => 'fr', 'title' => 'Leçon']);
        $this->page = $lesson->pages()->create(['order' => 1, 'kind' => LessonPage::KIND_DECISION]);
        $this->page->translations()->create(['language' => 'fr', 'title' => 'Avez-vous cru ?', 'choices' => ['Oui', 'Non']]);
    }

    private function answer(int $choice, ?string $device = 'phone-1')
    {
        return $this->postJson('/api/v1/lesson-pages/'.$this->page->id.'/decision', array_filter([
            'choice_index' => $choice,
            'device_id' => $device,
        ], fn ($v) => $v !== null));
    }

    private function stats(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole(AppRole::Admin->value);
        Sanctum::actingAs($admin);

        return $this->getJson('/api/v1/admin/stats')->assertOk()->json('data.decisions');
    }

    public function test_decision_page_is_served_with_its_choices(): void
    {
        $this->getJson('/api/v1/lessons/DC01')
            ->assertOk()
            ->assertJsonPath('data.pages.0.kind', 'decision')
            ->assertJsonPath('data.pages.0.choices', ['Oui', 'Non'])
            ->assertJsonPath('data.pages.0.correct_index', null);
    }

    public function test_visitor_answers_once_per_phone_and_can_change_it(): void
    {
        $this->answer(1)->assertCreated();
        $this->answer(0)->assertCreated();
        $this->answer(0, 'phone-2')->assertCreated();
        $this->answer(0, null)->assertUnprocessable();

        $this->assertSame(
            ['yes' => 2, 'no' => 0, 'yes_accounts' => 0, 'yes_visitors' => 2, 'yes_last_30_days' => 2],
            $this->stats(),
        );
    }

    public function test_visitor_answer_moves_to_the_account_after_sign_up(): void
    {
        $this->answer(0)->assertCreated();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->answer(0)->assertCreated();

        $stats = $this->stats();
        $this->assertSame(1, $stats['yes']);
        $this->assertSame(1, $stats['yes_accounts']);
    }

    public function test_only_decision_pages_take_answers(): void
    {
        $this->page->update(['kind' => LessonPage::KIND_CONTENT]);
        $this->answer(0)->assertNotFound();
    }
}
