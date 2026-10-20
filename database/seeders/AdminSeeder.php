<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Creates the first admin account from .env (ADMIN_EMAIL, ADMIN_NAME,
 * ADMIN_PASSWORD). Safe to run again: an existing admin keeps the password
 * they set in the app.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower((string) env('ADMIN_EMAIL', 'admin@disciplecoach.local'));
        $password = (string) env('ADMIN_PASSWORD', 'ChangeMe123!');

        Role::findOrCreate(AppRole::Admin->value);

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('ADMIN_NAME', 'Administrateur'),
                'password' => $password,
                'preferred_language' => 'fr',
                'is_active' => true,
            ],
        );

        if (! $user->hasRole(AppRole::Admin->value)) {
            $user->assignRole(AppRole::Admin->value);
        }
        if (! $user->is_active) {
            $user->forceFill(['is_active' => true])->save();
        }

        $this->command?->info($user->wasRecentlyCreated
            ? "Admin created: {$email} (change the password after the first login)"
            : "Admin already exists: {$email} (password unchanged)");
    }
}
