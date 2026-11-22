<?php

namespace Tests\Feature\Base;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_create_and_update_user_with_roles(): void
    {
        $this->admin();
        Role::create(['name' => 'Sales', 'guard_name' => 'web']);

        $id = $this->postJson('/api/users', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'roles' => ['Sales'],
        ])->assertCreated()->assertJsonPath('data.roles', ['Sales'])->json('data.id');

        $this->putJson("/api/users/{$id}", [
            'name' => 'Budi S',
            'email' => 'budi@example.com',
            'is_active' => false,
            'roles' => [],
        ])->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.roles', []);

        $this->assertDatabaseHas('users', ['id' => $id, 'name' => 'Budi S']);
    }

    public function test_list_users_with_search_and_archive_filter(): void
    {
        $this->admin();
        User::factory()->create(['name' => 'Siti Aminah']);
        User::factory()->create(['name' => 'Siti Lama', 'is_active' => false]);

        $this->getJson('/api/users?search=siti')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/users?search=siti&active=0')->assertOk()->assertJsonPath('data.0.name', 'Siti Lama');
        $this->getJson('/api/users?search=siti&active=all')->assertJsonPath('meta.total', 2);
    }

    public function test_cannot_deactivate_self_or_drop_own_super_admin(): void
    {
        $admin = $this->admin();

        $this->putJson("/api/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $this->putJson("/api/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'roles' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles');
    }

    public function test_only_super_admin_can_grant_super_admin(): void
    {
        $this->actingWithPermissions('base.user.manage', 'base.user.view');

        $this->postJson('/api/users', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'rahasia123', 'roles' => ['super-admin'],
        ])->assertForbidden();
    }

    public function test_deactivated_user_tokens_are_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;
        $this->admin();

        $this->putJson("/api/users/{$user->id}", [
            'name' => $user->name, 'email' => $user->email, 'is_active' => false,
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertNotEmpty($token);
    }

    public function test_view_permission_alone_cannot_create_users(): void
    {
        $this->actingWithPermissions('base.user.view');

        $this->getJson('/api/users')->assertOk();
        $this->postJson('/api/users', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'rahasia123'])
            ->assertForbidden();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users')->assertForbidden();
    }
}
