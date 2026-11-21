<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Role is not mass-assignable, so it is set explicitly after creation.
     */
    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_guest_is_redirected_to_login_from_admin_check(): void
    {
        $this->get('/admin-check')->assertRedirect(route('login'));
    }

    public function test_editor_gets_403_on_admin_check(): void
    {
        $editor = $this->userWithRole(User::ROLE_EDITOR);

        $this->actingAs($editor)->get('/admin-check')->assertStatus(403);
    }

    public function test_admin_gets_200_on_admin_check(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->get('/admin-check')->assertStatus(200);
    }

    public function test_dashboard_shows_name_and_role(): void
    {
        $user = $this->userWithRole(User::ROLE_EDITOR);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSee($user->name)
            ->assertSee('Role: editor');
    }
}