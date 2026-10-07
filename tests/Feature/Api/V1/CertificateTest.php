<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ContentStatus;
use App\Models\Certificate;
use App\Models\Level;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Certificates\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_issued_only_for_level_order_five(): void
    {
        Role::findOrCreate('disciple');

        $disciple = User::factory()->create();
        $disciple->assignRole('disciple');

        $level5 = Level::query()->create([
            'slug' => 'track_5',
            'order' => 5,
            'status' => ContentStatus::Published,
        ]);

        $quiz = Quiz::query()->create([
            'level_id' => $level5->id,
            'status' => ContentStatus::Published,
        ]);

        $attempt = QuizAttempt::query()->create([
            'user_id' => $disciple->id,
            'level_id' => $level5->id,
            'quiz_id' => $quiz->id,
            'score' => 1,
            'status' => 'approved',
            'mentor_status' => 'approved',
            'advancement_approved' => true,
            'advancement_approved_at' => now(),
            'submitted_at' => now(),
        ]);

        $service = app(CertificateService::class);
        $cert = $service->issueIfEligible($disciple, $attempt, $level5);

        $this->assertNotNull($cert);
        $this->assertTrue(Certificate::query()->where('user_id', $disciple->id)->exists());
        $this->assertNotNull($cert->pdf_path);

        Sanctum::actingAs($disciple);
        $this->getJson('/api/v1/me/certificate')
            ->assertOk()
            ->assertJsonPath('data.code', $cert->code);

        $this->get('/api/v1/me/certificate/download')
            ->assertOk();
    }

    public function test_certificate_not_issued_for_earlier_levels(): void
    {
        $disciple = User::factory()->create();
        $level1 = Level::query()->create([
            'slug' => 'track_1',
            'order' => 1,
            'status' => ContentStatus::Published,
        ]);

        $quiz = Quiz::query()->create([
            'level_id' => $level1->id,
            'status' => ContentStatus::Published,
        ]);

        $attempt = QuizAttempt::query()->create([
            'user_id' => $disciple->id,
            'level_id' => $level1->id,
            'quiz_id' => $quiz->id,
            'score' => 1,
            'status' => 'approved',
            'mentor_status' => 'approved',
            'advancement_approved' => true,
            'advancement_approved_at' => now(),
            'submitted_at' => now(),
        ]);

        $cert = app(CertificateService::class)->issueIfEligible($disciple, $attempt, $level1);
        $this->assertNull($cert);
        $this->assertSame(0, Certificate::query()->count());
    }
}
