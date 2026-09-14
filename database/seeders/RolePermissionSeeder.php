<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'curriculum.read',
            'curriculum.manage',
            'progress.own',
            'progress.coach',
            'progress.org',
            'quiz.submit',
            'quiz.review',
            'coaching.manage',
            'users.manage',
            'roles.assign_staff',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (AppRole::values() as $roleName) {
            Role::findOrCreate($roleName);
        }

        Role::findByName(AppRole::Disciple->value)->syncPermissions([
            'curriculum.read',
            'progress.own',
            'quiz.submit',
        ]);

        Role::findByName(AppRole::Coach->value)->syncPermissions([
            'curriculum.read',
            'progress.own',
            'progress.coach',
            'quiz.submit',
            'quiz.review',
            'coaching.manage',
        ]);

        Role::findByName(AppRole::Mentor->value)->syncPermissions([
            'curriculum.read',
            'progress.org',
            'progress.coach',
        ]);

        Role::findByName(AppRole::Editor->value)->syncPermissions([
            'curriculum.read',
            'curriculum.manage',
        ]);

        Role::findByName(AppRole::Admin->value)->syncPermissions(Permission::all());
    }
}
