<?php

use Database\Seeders\AdminSeeder;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Loads the base data with `php artisan migrate`, so a server gets the roles,
 * the first admin (ADMIN_* in .env) and the 3-part curriculum without a
 * separate db:seed step. Runs once; later content edits are made by the
 * admin in the app and are not overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolePermissionSeeder)->run();
        (new AdminSeeder)->run();
        (new CurriculumSeeder)->run();
    }

    public function down(): void
    {
        // Content is data, not schema: nothing to roll back.
    }
};
