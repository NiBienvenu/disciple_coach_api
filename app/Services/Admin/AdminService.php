<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Enums\MentorReviewStatus;
use App\Enums\RelationshipStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonTranslation;
use App\Models\Level;
use App\Models\LevelProgress;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Cache\UserCacheService;
use App\Services\Push\PushNotifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminService
{
    public function __construct(
        private readonly PushNotifier $push,
        private readonly UserCacheService $userCache,
    ) {}

    /**
     * @param  string  $filter  all | disciple | coach | admin | pending | disabled
     */
    public function listUsers(?string $search, string $filter): LengthAwarePaginator
    {
        $query = User::query()->with('roles')->orderBy('name');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.strtolower(trim($search)).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term]));
        }

        match ($filter) {
            'disciple', 'coach', 'admin' => $query->role($filter),
            'pending' => $query->role('coach')->where('coach_status', 'pending'),
            'disabled' => $query->where('is_active', false),
            default => null,
        };

        return $query->paginate(50);
    }

    public function setActive(User $admin, User $user, bool $active): User
    {
        if ($admin->id === $user->id && ! $active) {
            throw ValidationException::withMessages(['active' => ["You can't deactivate your own account."]]);
        }

        $user->forceFill(['is_active' => $active])->save();

        if (! $active) {
            // Sign the user out everywhere and stop pushes to their devices.
            $user->tokens()->delete();
            $user->deviceTokens()->delete();
        }

        $this->userCache->forgetProfile($user->id);

        return $user->fresh()->load('roles');
    }

    /**
     * @param  string  $status  approved | pending | rejected
     */
    public function setCoachStatus(User $user, string $status): User
    {
        DB::transaction(function () use ($user, $status) {
            if ($status === 'rejected') {
                // Declined coach request: the account becomes a disciple account.
                $user->removeRole('coach');
                if (! $user->hasRole('disciple')) {
                    $user->assignRole('disciple');
                }
                $user->forceFill(['coach_status' => null])->save();

                return;
            }

            if (! $user->hasRole('coach')) {
                $user->assignRole('coach');
            }
            $user->forceFill(['coach_status' => $status])->save();
        });

        $this->userCache->forgetProfile($user->id);

        if ($status === 'approved') {
            $this->push->notify($user, ['fr' => 'Compte coach approuvé', 'en' => 'Coach account approved'], ['fr' => 'Vous pouvez maintenant accompagner des disciples.', 'en' => 'You can now coach disciples.'], [
                'type' => 'coach_approved',
            ]);
        } elseif ($status === 'rejected') {
            $this->push->notify($user, ['fr' => 'Demande de coach refusée', 'en' => 'Coach request declined'], ['fr' => 'Votre compte reste un compte disciple.', 'en' => 'Your account stays a disciple account.'], [
                'type' => 'coach_rejected',
            ]);
        }

        return $user->fresh()->load('roles');
    }

    /**
     * Every coach with their active disciples and where each disciple is in
     * the curriculum. The current level is the first published level (by
     * order) that is not approved yet; `finished` is true once all are.
     *
     * @return list<array<string, mixed>>
     */
    public function coachRoster(string $language): array
    {
        $coaches = User::query()->role('coach')->orderBy('name')
            ->get(['id', 'name', 'email', 'coach_status', 'is_active']);

        $relationships = CoachDiscipleRelationship::query()
            ->whereIn('coach_id', $coaches->pluck('id')->all() ?: [0])
            ->where('status', RelationshipStatus::Active)
            ->with('disciple:id,name,email,is_active')
            ->orderBy('started_at')
            ->get()
            ->groupBy('coach_id');

        $discipleIds = $relationships->flatten(1)->pluck('disciple_id')->unique()->values()->all() ?: [0];

        $levels = Level::query()
            ->where('status', ContentStatus::Published)
            ->orderBy('order')
            ->with('translations')
            ->withCount(['lessons' => fn ($q) => $q->where('status', ContentStatus::Published)])
            ->get();

        $progressByUser = LevelProgress::query()
            ->whereIn('user_id', $discipleIds)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('level_id'));

        $lessonsDoneByUser = LessonProgress::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->whereIn('lesson_progress.user_id', $discipleIds)
            ->where('lesson_progress.status', 'completed')
            ->where('lessons.status', ContentStatus::Published)
            ->groupBy('lesson_progress.user_id')
            ->selectRaw('lesson_progress.user_id, COUNT(*) as total')
            ->pluck('total', 'user_id');

        $lessonsTotal = (int) $levels->sum('lessons_count');
        $statusOf = fn ($lp) => $lp?->status instanceof \BackedEnum ? $lp->status->value : ($lp?->status ?? 'locked');

        return $coaches->map(fn (User $coach) => [
            'coach' => [
                'id' => $coach->id,
                'name' => $coach->name,
                'email' => $coach->email,
                'coach_status' => $coach->coach_status,
                'is_active' => (bool) $coach->is_active,
            ],
            'disciples' => $relationships->get($coach->id, collect())
                ->filter(fn (CoachDiscipleRelationship $rel) => $rel->disciple !== null)
                ->map(function (CoachDiscipleRelationship $rel) use ($levels, $progressByUser, $lessonsDoneByUser, $lessonsTotal, $statusOf, $language) {
                    $progress = $progressByUser->get($rel->disciple_id, collect());
                    $current = $levels->first(fn (Level $l) => $statusOf($progress->get($l->id)) !== 'approved');
                    $approved = $levels->filter(fn (Level $l) => $statusOf($progress->get($l->id)) === 'approved')->count();

                    return [
                        'id' => $rel->disciple->id,
                        'name' => $rel->disciple->name,
                        'email' => $rel->disciple->email,
                        'is_active' => (bool) $rel->disciple->is_active,
                        'started_at' => $rel->started_at?->toIso8601String(),
                        'current_level' => $current === null ? null : [
                            'id' => $current->id,
                            'order' => $current->order,
                            'part' => $current->part,
                            'title' => $current->translationFor($language)?->title ?? $current->slug,
                            'status' => $statusOf($progress->get($current->id)),
                        ],
                        'finished' => $current === null && $levels->isNotEmpty(),
                        'levels_approved' => $approved,
                        'levels_total' => $levels->count(),
                        'lessons_completed' => (int) ($lessonsDoneByUser[$rel->disciple_id] ?? 0),
                        'lessons_total' => $lessonsTotal,
                    ];
                })->values()->all(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $byRole = fn (string $role) => User::query()->role($role)->count();

        $levels = Level::query()
            ->withCount('lessons')
            ->with(['translations' => fn ($q) => $q->where('language', 'fr')])
            ->orderBy('order')
            ->get();

        $totalLessons = Lesson::query()->count();
        $completedByLevel = LessonProgress::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->where('lesson_progress.status', 'completed')
            ->groupBy('lessons.level_id')
            ->selectRaw('lessons.level_id, COUNT(*) as total')
            ->pluck('total', 'level_id');

        $translations = LessonTranslation::query()
            ->selectRaw('language, COUNT(*) as total')
            ->groupBy('language')
            ->pluck('total', 'language');

        $attempts = QuizAttempt::query()
            ->selectRaw('mentor_status, COUNT(*) as total, AVG(score) as avg_score')
            ->groupBy('mentor_status')
            ->get();

        return [
            'users' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'disabled' => User::query()->where('is_active', false)->count(),
                'disciples' => $byRole('disciple'),
                'coaches_approved' => User::query()->role('coach')->where('coach_status', 'approved')->count(),
                'coaches_pending' => User::query()->role('coach')->where('coach_status', 'pending')->count(),
                'admins' => $byRole('admin'),
                'active_relationships' => CoachDiscipleRelationship::query()->where('status', RelationshipStatus::Active)->count(),
            ],
            'content' => [
                'parts' => Level::query()->distinct()->count('part'),
                'levels' => $levels->count(),
                'lessons' => $totalLessons,
                'quiz_questions' => QuizQuestion::query()->count(),
                'translations' => $translations,
                'lessons_per_level' => $levels->map(fn (Level $l) => [
                    'level_id' => $l->id,
                    'part' => $l->part,
                    'title' => $l->translations->first()?->title ?? $l->slug,
                    'lessons' => $l->lessons_count,
                    'completions' => (int) ($completedByLevel[$l->id] ?? 0),
                ])->values(),
            ],
            'progress' => [
                'lessons_completed' => LessonProgress::query()->where('status', 'completed')->count(),
                'quiz_attempts' => (int) $attempts->sum('total'),
                'quiz_pending' => (int) ($attempts->firstWhere('mentor_status', MentorReviewStatus::Pending->value)?->total ?? 0),
                'quiz_approved' => (int) ($attempts->firstWhere('mentor_status', MentorReviewStatus::Approved->value)?->total ?? 0),
                'quiz_changes_requested' => (int) ($attempts->firstWhere('mentor_status', MentorReviewStatus::ChangesRequested->value)?->total ?? 0),
                'average_score' => $attempts->sum('total') > 0
                    ? round((float) QuizAttempt::query()->avg('score') * 100)
                    : null,
            ],
        ];
    }
}
