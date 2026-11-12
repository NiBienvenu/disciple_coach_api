<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppRole;
use App\Enums\RelationshipStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Models\RelationshipMessage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $disciple;

    private CoachDiscipleRelationship $relationship;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->coach = User::factory()->create(['coach_status' => 'approved']);
        $this->coach->assignRole(AppRole::Coach->value);
        $this->disciple = User::factory()->create();
        $this->disciple->assignRole(AppRole::Disciple->value);

        $this->relationship = CoachDiscipleRelationship::query()->create([
            'coach_id' => $this->coach->id,
            'disciple_id' => $this->disciple->id,
            'status' => RelationshipStatus::Active,
            'started_at' => now(),
            'invite_code' => 'TEST02',
        ]);
    }

    protected function tearDown(): void
    {
        // Remove the files these tests wrote into public/uploads/chat.
        foreach (RelationshipMessage::query()->whereNotNull('attachment_path')->pluck('attachment_path') as $path) {
            @unlink(public_path(ltrim($path, '/')));
        }
        @rmdir(public_path('uploads/chat/'.$this->relationship->id));
        parent::tearDown();
    }

    private function url(string $suffix = ''): string
    {
        return '/api/v1/relationships/'.$this->relationship->id.'/messages'.$suffix;
    }

    public function test_reply_to_a_message_returns_the_quoted_message(): void
    {
        Sanctum::actingAs($this->disciple);
        $first = $this->postJson($this->url(), ['body' => 'Bonjour coach'])->assertCreated();

        Sanctum::actingAs($this->coach);
        $this->postJson($this->url(), ['body' => 'Bonjour !', 'reply_to_id' => $first->json('data.id')])
            ->assertCreated()
            ->assertJsonPath('data.type', 'text')
            ->assertJsonPath('data.reply_to.id', $first->json('data.id'))
            ->assertJsonPath('data.reply_to.body', 'Bonjour coach');

        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.0.reply_to.body', 'Bonjour coach');
    }

    public function test_cannot_reply_to_a_message_from_another_chat(): void
    {
        Sanctum::actingAs($this->coach);
        $this->postJson($this->url(), ['body' => 'x', 'reply_to_id' => 999999])
            ->assertStatus(422);
    }

    public function test_photo_is_saved_in_public_uploads_under_a_random_name(): void
    {
        Sanctum::actingAs($this->disciple);
        $response = $this->post($this->url(), [
            'file' => UploadedFile::fake()->image('photo.jpg', 40, 40),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.attachment.name', 'photo.jpg');

        $path = RelationshipMessage::query()->findOrFail($response->json('data.id'))->attachment_path;
        $this->assertStringStartsWith('/uploads/chat/'.$this->relationship->id.'/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertFileExists(public_path(ltrim($path, '/')));
        $this->assertSame(url($path), $response->json('data.attachment.url'));
    }

    public function test_script_files_can_never_keep_a_runnable_extension(): void
    {
        Sanctum::actingAs($this->coach);
        $response = $this->post($this->url(), [
            'file' => UploadedFile::fake()->createWithContent('hack.php', '<?php echo 1;'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.type', 'file')
            ->assertJsonPath('data.attachment.name', 'hack.php');

        $path = RelationshipMessage::query()->findOrFail($response->json('data.id'))->attachment_path;
        $this->assertStringEndsWith('.bin', $path);
    }

    public function test_voice_note_keeps_its_duration_and_shows_in_chat_list(): void
    {
        Sanctum::actingAs($this->disciple);
        $this->post($this->url(), [
            'file' => UploadedFile::fake()->create('voice.m4a', 30, 'audio/mp4'),
            'kind' => 'audio',
            'duration_ms' => 12500,
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.type', 'audio')
            ->assertJsonPath('data.attachment.duration_ms', 12500);

        Sanctum::actingAs($this->coach);
        $this->getJson('/api/v1/relationships')->assertOk()
            ->assertJsonPath('data.0.last_message.type', 'audio')
            ->assertJsonPath('data.0.last_message.duration_ms', 12500);
    }

    public function test_document_is_sent_as_file(): void
    {
        Sanctum::actingAs($this->coach);
        $this->post($this->url(), [
            'file' => UploadedFile::fake()->create('plan.pdf', 50, 'application/pdf'),
            'body' => 'Voici le plan',
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.type', 'file')
            ->assertJsonPath('data.body', 'Voici le plan')
            ->assertJsonPath('data.attachment.name', 'plan.pdf');
    }

    public function test_empty_message_is_refused(): void
    {
        Sanctum::actingAs($this->coach);
        $this->postJson($this->url(), [])->assertStatus(422);
    }

    public function test_messages_list_says_when_the_other_person_read_the_chat(): void
    {
        Sanctum::actingAs($this->coach);
        $sent = $this->postJson($this->url(), ['body' => 'Salut'])->assertCreated();

        Sanctum::actingAs($this->disciple);
        $this->postJson('/api/v1/relationships/'.$this->relationship->id.'/read')->assertOk();

        Sanctum::actingAs($this->coach);
        $readAt = $this->getJson($this->url('?after='.($sent->json('data.id') - 1)))
            ->assertOk()
            ->json('meta.other_read_at');
        $this->assertNotNull($readAt);
    }
}
