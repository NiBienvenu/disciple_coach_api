<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppRole;
use App\Enums\RelationshipStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Note;
use App\Models\RelationshipMessage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachingTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $disciple;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->coach = User::factory()->create(['email' => 'coach@example.com']);
        $this->coach->assignRole(AppRole::Coach->value);

        $this->disciple = User::factory()->create(['email' => 'disciple@example.com']);
        $this->disciple->assignRole(AppRole::Disciple->value);

        $this->outsider = User::factory()->create(['email' => 'outsider@example.com']);
        $this->outsider->assignRole(AppRole::Disciple->value);
    }

    public function test_coach_creates_invite_and_disciple_redeems_to_active_relationship(): void
    {
        Sanctum::actingAs($this->coach);

        $inviteResponse = $this->postJson('/api/v1/invites')
            ->assertCreated()
            ->assertJsonPath('success', true);

        $code = $inviteResponse->json('data.code');
        $this->assertSame(6, strlen($code));
        $this->assertDatabaseHas('invites', [
            'code' => $code,
            'coach_id' => $this->coach->id,
            'used' => false,
        ]);

        Sanctum::actingAs($this->disciple);
        $this->postJson('/api/v1/invites/'.$code.'/redeem')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coach_id', $this->coach->id)
            ->assertJsonPath('data.disciple_id', $this->disciple->id)
            ->assertJsonPath('data.status', RelationshipStatus::Active->value);

        $this->assertDatabaseHas('invites', [
            'code' => $code,
            'used' => true,
        ]);

        $this->assertDatabaseHas('coach_disciple_relationships', [
            'coach_id' => $this->coach->id,
            'disciple_id' => $this->disciple->id,
            'invite_code' => $code,
            'status' => RelationshipStatus::Active->value,
        ]);
    }

    public function test_redeem_twice_fails(): void
    {
        Sanctum::actingAs($this->coach);
        $code = $this->postJson('/api/v1/invites')->json('data.code');

        Sanctum::actingAs($this->disciple);
        $this->postJson('/api/v1/invites/'.$code.'/redeem')->assertCreated();

        Sanctum::actingAs($this->outsider);
        $this->postJson('/api/v1/invites/'.$code.'/redeem')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('coach_disciple_relationships', 1);
    }

    public function test_messages_cursor_pagination(): void
    {
        $relationship = $this->createActiveRelationship();

        Sanctum::actingAs($this->coach);
        for ($i = 1; $i <= 35; $i++) {
            $this->postJson('/api/v1/relationships/'.$relationship->id.'/messages', [
                'body' => 'Message '.$i,
            ])->assertCreated();
        }

        $first = $this->getJson('/api/v1/relationships/'.$relationship->id.'/messages')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.per_page', 30);

        $this->assertCount(30, $first->json('data'));
        $nextCursor = $first->json('meta.next_cursor');
        $this->assertNotEmpty($nextCursor);

        $second = $this->getJson('/api/v1/relationships/'.$relationship->id.'/messages?cursor='.$nextCursor)
            ->assertOk()
            ->assertJsonPath('meta.has_more', false);

        $this->assertCount(5, $second->json('data'));
        $this->assertDatabaseCount('relationship_messages', 35);
    }

    public function test_disciple_cannot_see_private_coach_note(): void
    {
        $relationship = $this->createActiveRelationship();

        Note::query()->create([
            'author_id' => $this->coach->id,
            'disciple_id' => $this->disciple->id,
            'relationship_id' => $relationship->id,
            'body' => 'Private coach thoughts',
            'is_shared' => false,
        ]);

        Note::query()->create([
            'author_id' => $this->coach->id,
            'disciple_id' => $this->disciple->id,
            'relationship_id' => $relationship->id,
            'body' => 'Shared encouragement',
            'is_shared' => true,
        ]);

        Sanctum::actingAs($this->disciple);
        $response = $this->getJson('/api/v1/relationships/'.$relationship->id)
            ->assertOk();

        $bodies = collect($response->json('data.notes'))->pluck('body')->all();
        $this->assertContains('Shared encouragement', $bodies);
        $this->assertNotContains('Private coach thoughts', $bodies);

        Sanctum::actingAs($this->coach);
        $coachView = $this->getJson('/api/v1/relationships/'.$relationship->id)
            ->assertOk();

        $coachBodies = collect($coachView->json('data.notes'))->pluck('body')->all();
        $this->assertContains('Private coach thoughts', $coachBodies);
        $this->assertContains('Shared encouragement', $coachBodies);
    }

    public function test_only_parties_can_list_messages(): void
    {
        $relationship = $this->createActiveRelationship();

        RelationshipMessage::query()->create([
            'relationship_id' => $relationship->id,
            'sender_id' => $this->coach->id,
            'body' => 'Hello disciple',
        ]);

        Sanctum::actingAs($this->coach);
        $this->getJson('/api/v1/relationships/'.$relationship->id.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Hello disciple');

        Sanctum::actingAs($this->disciple);
        $this->getJson('/api/v1/relationships/'.$relationship->id.'/messages')
            ->assertOk();

        Sanctum::actingAs($this->outsider);
        $this->getJson('/api/v1/relationships/'.$relationship->id.'/messages')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_coach_dashboard_returns_structure(): void
    {
        $relationship = $this->createActiveRelationship();

        Sanctum::actingAs($this->coach);
        $this->postJson('/api/v1/relationships/'.$relationship->id.'/sessions', [
            'title' => 'Check-in',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'duration_min' => 45,
        ])->assertCreated();

        $this->getJson('/api/v1/coach/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'assigned_disciples' => [
                        [
                            'relationship_id',
                            'status',
                            'disciple' => ['id', 'name', 'email'],
                            'pending_review_count',
                        ],
                    ],
                    'pending_reviews',
                    'upcoming_sessions' => [
                        [
                            'id',
                            'relationship_id',
                            'scheduled_at',
                            'status',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.assigned_disciples.0.disciple.id', $this->disciple->id)
            ->assertJsonPath('data.upcoming_sessions.0.title', 'Check-in');
    }

    private function createActiveRelationship(): CoachDiscipleRelationship
    {
        return CoachDiscipleRelationship::query()->create([
            'coach_id' => $this->coach->id,
            'disciple_id' => $this->disciple->id,
            'status' => RelationshipStatus::Active,
            'started_at' => now(),
            'invite_code' => 'TEST01',
            'last_read_at' => [
                (string) $this->coach->id => now()->toIso8601String(),
                (string) $this->disciple->id => now()->toIso8601String(),
            ],
        ]);
    }
}
