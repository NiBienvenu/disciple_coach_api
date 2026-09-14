<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Enums\GoalStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\RelationshipStatus;
use App\Enums\SessionStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\CoachingSession;
use App\Models\Goal;
use App\Models\Invite;
use App\Models\Lesson;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\RelationshipMessage;
use App\Models\User;
use App\Services\Progress\LevelProgressionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    /** @var array<string, User> */
    private array $usersByKey = [];

    /** @var array<string, CoachDiscipleRelationship> */
    private array $relationshipsByKey = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder skipped in production.');

            return;
        }

        $path = $this->resolveSeedPath();
        if ($path === null) {
            $this->command?->warn('demo_seed.json not found — skipping demo data.');

            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $password = (string) env('DEMO_SEED_PASSWORD', 'password');

        $this->seedUsers($payload['users'] ?? [], $password);
        $this->seedRelationships($payload['relationships'] ?? []);
        $this->seedInvites($payload['invites'] ?? []);
        $this->seedLessonProgress($payload['lesson_progress'] ?? []);
        $this->seedSessions($payload['coaching_sessions'] ?? []);
        $this->seedGoals($payload['goals'] ?? []);
        $this->seedMessages($payload['messages'] ?? []);
        $this->seedNotes($payload['notes'] ?? []);

        $this->command?->info('Demo data seeded from '.$path);
        $this->command?->info('Login example: POST /api/v1/auth/login — coach@disciplecoach.local / '.$password);
    }

    /**
     * @param  list<array<string, mixed>>  $users
     */
    private function seedUsers(array $users, string $password): void
    {
        foreach ($users as $row) {
            $key = (string) $row['key'];
            $email = strtolower((string) $row['email']);

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => (string) $row['name'],
                    'phone' => $row['phone'] ?? null,
                    'password' => Hash::make($password),
                    'preferred_language' => $row['preferred_language'] ?? 'fr',
                    'is_active' => true,
                ],
            );

            $roles = array_values(array_unique($row['roles'] ?? []));
            foreach ($roles as $roleName) {
                if (in_array($roleName, AppRole::values(), true)) {
                    Role::findOrCreate($roleName);
                }
            }
            if ($roles !== []) {
                $user->syncRoles($roles);
            }

            $this->usersByKey[$key] = $user;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $relationships
     */
    private function seedRelationships(array $relationships): void
    {
        foreach ($relationships as $row) {
            $coach = $this->usersByKey[$row['coach']] ?? null;
            $disciple = $this->usersByKey[$row['disciple']] ?? null;
            if ($coach === null || $disciple === null) {
                continue;
            }

            $status = RelationshipStatus::tryFrom((string) ($row['status'] ?? 'active'))
                ?? RelationshipStatus::Active;

            $relationship = CoachDiscipleRelationship::query()->updateOrCreate(
                [
                    'coach_id' => $coach->id,
                    'disciple_id' => $disciple->id,
                ],
                [
                    'invite_code' => $row['invite_code'] ?? null,
                    'status' => $status,
                    'started_at' => now()->subDays(7),
                    'last_read_at' => [
                        (string) $coach->id => now()->subHour()->toIso8601String(),
                        (string) $disciple->id => now()->subHours(2)->toIso8601String(),
                    ],
                ],
            );

            if (isset($row['key'])) {
                $this->relationshipsByKey[(string) $row['key']] = $relationship;
            }

            Invite::query()->updateOrCreate(
                ['code' => (string) ($row['invite_code'] ?? 'PAIR01')],
                [
                    'coach_id' => $coach->id,
                    'used' => true,
                    'used_at' => now()->subDays(7),
                    'expires_at' => now()->addDays(7),
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $invites
     */
    private function seedInvites(array $invites): void
    {
        foreach ($invites as $row) {
            $coach = $this->usersByKey[$row['coach']] ?? null;
            if ($coach === null) {
                continue;
            }

            $days = (int) ($row['expires_in_days'] ?? 7);

            Invite::query()->updateOrCreate(
                ['code' => (string) $row['code']],
                [
                    'coach_id' => $coach->id,
                    'used' => (bool) ($row['used'] ?? false),
                    'used_at' => ($row['used'] ?? false) ? now() : null,
                    'expires_at' => now()->addDays($days),
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function seedLessonProgress(array $rows): void
    {
        /** @var LevelProgressionService $progression */
        $progression = app(LevelProgressionService::class);

        foreach ($rows as $row) {
            $user = $this->usersByKey[$row['user']] ?? null;
            if ($user === null) {
                continue;
            }

            $lesson = Lesson::query()->where('code', (string) $row['lesson_code'])->first();
            if ($lesson === null) {
                $this->command?->warn('Lesson not found for code: '.$row['lesson_code']);

                continue;
            }

            $status = LessonProgressStatus::tryFrom((string) ($row['status'] ?? 'completed'))
                ?? LessonProgressStatus::Completed;

            $progression->ensureBootstrap($user);
            $progression->completeLesson($user, $lesson, $status);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     */
    private function seedSessions(array $sessions): void
    {
        foreach ($sessions as $row) {
            $relationship = $this->relationshipByKey((string) $row['relationship']);
            $creator = $this->usersByKey[$row['created_by']] ?? null;
            if ($relationship === null || $creator === null) {
                continue;
            }

            $scheduledAt = $this->parseRelativeTime($row['scheduled_at'] ?? '+3 days');

            CoachingSession::query()->updateOrCreate(
                [
                    'relationship_id' => $relationship->id,
                    'title' => (string) ($row['title'] ?? 'Session'),
                    'scheduled_at' => $scheduledAt,
                ],
                [
                    'coach_id' => $relationship->coach_id,
                    'disciple_id' => $relationship->disciple_id,
                    'created_by' => $creator->id,
                    'duration_min' => (int) ($row['duration_min'] ?? 60),
                    'agenda' => $row['agenda'] ?? null,
                    'status' => SessionStatus::tryFrom((string) ($row['status'] ?? 'scheduled'))
                        ?? SessionStatus::Scheduled,
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $goals
     */
    private function seedGoals(array $goals): void
    {
        foreach ($goals as $row) {
            $relationship = $this->relationshipByKey((string) $row['relationship']);
            $creator = $this->usersByKey[$row['created_by']] ?? null;
            if ($relationship === null || $creator === null) {
                continue;
            }

            $goal = Goal::query()->updateOrCreate(
                [
                    'relationship_id' => $relationship->id,
                    'title' => (string) $row['title'],
                ],
                [
                    'created_by' => $creator->id,
                    'disciple_id' => $relationship->disciple_id,
                    'coach_id' => $relationship->coach_id,
                    'description' => $row['description'] ?? null,
                    'status' => GoalStatus::tryFrom((string) ($row['status'] ?? 'in_progress'))
                        ?? GoalStatus::InProgress,
                    'target_date' => isset($row['target_date'])
                        ? $this->parseRelativeTime($row['target_date'])->toDateString()
                        : null,
                ],
            );

            $goal->milestones()->delete();

            foreach (array_values($row['milestones'] ?? []) as $index => $milestone) {
                Milestone::query()->create([
                    'goal_id' => $goal->id,
                    'title' => (string) ($milestone['title'] ?? 'Milestone'),
                    'done' => (bool) ($milestone['done'] ?? false),
                    'done_at' => ($milestone['done'] ?? false) ? now()->subDays(3) : null,
                    'order' => $index + 1,
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function seedMessages(array $messages): void
    {
        foreach ($messages as $row) {
            $relationship = $this->relationshipByKey((string) $row['relationship']);
            $sender = $this->usersByKey[$row['sender']] ?? null;
            if ($relationship === null || $sender === null) {
                continue;
            }

            $sentAt = $this->parseRelativeTime($row['sent_at'] ?? '-1 day');

            RelationshipMessage::query()->create([
                'relationship_id' => $relationship->id,
                'sender_id' => $sender->id,
                'body' => (string) $row['body'],
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        }

        foreach ($this->relationshipKeysFromPayload($messages) as $relKey) {
            $relationship = $this->relationshipByKey($relKey);
            if ($relationship !== null) {
                $relationship->update(['last_message_at' => now()]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $notes
     */
    private function seedNotes(array $notes): void
    {
        foreach ($notes as $row) {
            $relationship = $this->relationshipByKey((string) $row['relationship']);
            $author = $this->usersByKey[$row['author']] ?? null;
            $disciple = $this->usersByKey[$row['disciple']] ?? null;
            if ($relationship === null || $author === null || $disciple === null) {
                continue;
            }

            Note::query()->updateOrCreate(
                [
                    'relationship_id' => $relationship->id,
                    'author_id' => $author->id,
                    'body' => (string) $row['body'],
                ],
                [
                    'disciple_id' => $disciple->id,
                    'is_shared' => (bool) ($row['is_shared'] ?? false),
                ],
            );
        }
    }

    private function relationshipByKey(string $key): ?CoachDiscipleRelationship
    {
        return $this->relationshipsByKey[$key] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<string>
     */
    private function relationshipKeysFromPayload(array $messages): array
    {
        $keys = [];
        foreach ($messages as $row) {
            $keys[] = (string) $row['relationship'];
        }

        return array_values(array_unique($keys));
    }

    private function parseRelativeTime(string $value): Carbon
    {
        if (str_starts_with($value, '+') || str_starts_with($value, '-')) {
            return now()->modify($value);
        }

        return Carbon::parse($value);
    }

    private function resolveSeedPath(): ?string
    {
        $path = database_path('seeders/demo/demo_seed.json');

        return File::isFile($path) ? $path : null;
    }
}
