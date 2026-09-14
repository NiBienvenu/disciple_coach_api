<?php

namespace App\Services\Coaching;

use App\Enums\GoalStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoalService
{
    /**
     * @return Collection<int, Goal>
     */
    public function list(CoachDiscipleRelationship $relationship): Collection
    {
        return Goal::query()
            ->where('relationship_id', $relationship->id)
            ->with('milestones')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(CoachDiscipleRelationship $relationship, User $actor, array $data): Goal
    {
        if (! $relationship->isActive()) {
            throw ValidationException::withMessages([
                'relationship' => ['Cannot create goals on an ended relationship.'],
            ]);
        }

        return DB::transaction(function () use ($relationship, $actor, $data) {
            $goal = Goal::query()->create([
                'relationship_id' => $relationship->id,
                'created_by' => $actor->id,
                'disciple_id' => $relationship->disciple_id,
                'coach_id' => $relationship->coach_id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? GoalStatus::NotStarted,
                'target_date' => $data['target_date'] ?? null,
            ]);

            $milestones = $data['milestones'] ?? [];
            foreach (array_values($milestones) as $index => $milestone) {
                $goal->milestones()->create([
                    'title' => $milestone['title'],
                    'description' => $milestone['description'] ?? null,
                    'order' => $milestone['order'] ?? $index,
                    'done' => false,
                ]);
            }

            return $goal->load('milestones');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Goal $goal, array $data): Goal
    {
        return DB::transaction(function () use ($goal, $data) {
            $payload = collect($data)->only([
                'title',
                'description',
                'status',
                'target_date',
            ])->all();

            if (isset($payload['status'])) {
                $status = $payload['status'] instanceof GoalStatus
                    ? $payload['status']
                    : GoalStatus::from((string) $payload['status']);

                if ($status === GoalStatus::Completed && $goal->completed_at === null) {
                    $payload['completed_at'] = now();
                }

                if ($status !== GoalStatus::Completed) {
                    $payload['completed_at'] = null;
                }

                $payload['status'] = $status;
            }

            $goal->update($payload);

            if (array_key_exists('milestones', $data) && is_array($data['milestones'])) {
                $keepIds = [];
                foreach (array_values($data['milestones']) as $index => $row) {
                    if (isset($row['id'])) {
                        $milestone = Milestone::query()
                            ->where('goal_id', $goal->id)
                            ->where('id', $row['id'])
                            ->first();

                        if ($milestone !== null) {
                            $milestone->update([
                                'title' => $row['title'] ?? $milestone->title,
                                'description' => $row['description'] ?? $milestone->description,
                                'order' => $row['order'] ?? $index,
                            ]);
                            $keepIds[] = $milestone->id;
                        }
                    } else {
                        $created = $goal->milestones()->create([
                            'title' => $row['title'],
                            'description' => $row['description'] ?? null,
                            'order' => $row['order'] ?? $index,
                            'done' => false,
                        ]);
                        $keepIds[] = $created->id;
                    }
                }

                $stale = Milestone::query()->where('goal_id', $goal->id);
                if ($keepIds !== []) {
                    $stale->whereNotIn('id', $keepIds);
                }
                $stale->delete();
            }

            return $goal->fresh('milestones');
        });
    }

    public function delete(Goal $goal): void
    {
        $goal->delete();
    }

    public function toggleMilestone(Milestone $milestone, ?bool $done = null): Milestone
    {
        $newDone = $done ?? ! $milestone->done;

        $milestone->update([
            'done' => $newDone,
            'done_at' => $newDone ? now() : null,
        ]);

        $goal = $milestone->goal()->with('milestones')->first();
        if ($goal !== null) {
            $allDone = $goal->milestones->isNotEmpty()
                && $goal->milestones->every(fn (Milestone $m) => $m->done);

            if ($allDone && $goal->status !== GoalStatus::Completed) {
                $goal->update([
                    'status' => GoalStatus::Completed,
                    'completed_at' => now(),
                ]);
            } elseif (! $allDone && $goal->status === GoalStatus::Completed) {
                $goal->update([
                    'status' => GoalStatus::InProgress,
                    'completed_at' => null,
                ]);
            } elseif ($newDone && $goal->status === GoalStatus::NotStarted) {
                $goal->update(['status' => GoalStatus::InProgress]);
            }
        }

        return $milestone->fresh('goal.milestones');
    }
}
