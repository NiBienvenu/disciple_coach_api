<?php

namespace App\Services\Coaching;

use App\Enums\RelationshipStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InviteService
{
    private const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 6;

    private const EXPIRY_DAYS = 7;

    private const MAX_ATTEMPTS = 8;

    public function create(User $coach): Invite
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->generateCode();

            if (Invite::query()->where('code', $code)->exists()) {
                continue;
            }

            return Invite::query()->create([
                'code' => $code,
                'coach_id' => $coach->id,
                'used' => false,
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            ]);
        }

        throw new RuntimeException('Could not generate a unique invite code.');
    }

    public function redeem(User $disciple, string $code): CoachDiscipleRelationship
    {
        $normalized = strtoupper(trim($code));

        return DB::transaction(function () use ($disciple, $normalized) {
            /** @var Invite|null $invite */
            $invite = Invite::query()
                ->where('code', $normalized)
                ->lockForUpdate()
                ->first();

            if ($invite === null) {
                throw ValidationException::withMessages([
                    'code' => ['Invite code not found.'],
                ]);
            }

            if ($invite->used) {
                throw ValidationException::withMessages([
                    'code' => ['This invite code has already been used.'],
                ]);
            }

            if ($invite->isExpired()) {
                throw ValidationException::withMessages([
                    'code' => ['This invite code has expired.'],
                ]);
            }

            if ((int) $invite->coach_id === (int) $disciple->id) {
                throw ValidationException::withMessages([
                    'code' => ['You cannot redeem your own invite code.'],
                ]);
            }

            $existing = CoachDiscipleRelationship::query()
                ->where('coach_id', $invite->coach_id)
                ->where('disciple_id', $disciple->id)
                ->where('status', RelationshipStatus::Active)
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'code' => ['An active relationship with this coach already exists.'],
                ]);
            }

            $invite->update([
                'used' => true,
                'used_at' => now(),
            ]);

            return CoachDiscipleRelationship::query()->create([
                'invite_code' => $invite->code,
                'coach_id' => $invite->coach_id,
                'disciple_id' => $disciple->id,
                'status' => RelationshipStatus::Active,
                'started_at' => now(),
                'last_read_at' => [
                    (string) $invite->coach_id => now()->toIso8601String(),
                    (string) $disciple->id => now()->toIso8601String(),
                ],
            ]);
        });
    }

    private function generateCode(): string
    {
        $chars = self::CODE_CHARS;
        $max = strlen($chars) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        return $code;
    }
}
