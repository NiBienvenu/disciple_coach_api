<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_creates_users_and_open_invite(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(CurriculumSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'coach@disciplecoach.local']);
        $this->assertDatabaseHas('users', ['email' => 'disciple@disciplecoach.local']);

        $coach = User::query()->where('email', 'coach@disciplecoach.local')->first();
        $this->assertTrue($coach->hasRole('coach'));

        $invite = Invite::query()->where('code', 'GROW01')->first();
        $this->assertNotNull($invite);
        $this->assertFalse($invite->used);
    }
}
