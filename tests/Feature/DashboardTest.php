<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        DB::table('Employee')->insert([
            'username' => $user->email,
            'passwordHash' => bcrypt('password'),
            'name' => $user->name,
            'email' => $user->email,
            'emailVerified' => true,
            'status' => 'ACTIVE',
            'isDefaultAdmin' => true,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);
        DB::table('Store')->insert([
            'id' => 'default-store',
            'slug' => 'default',
            'name' => 'Default Store',
            'timezone' => 'America/Los_Angeles',
            'status' => 'ACTIVE',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect('/default');

        $this->get('/default')->assertOk();
    }
}
