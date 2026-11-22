<?php

namespace Tests\Feature\Base;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_create_role_with_permissions_and_it_grants_access(): void
    {
        $this->admin();
        $this->enableModules('Contacts');

        $roleId = $this->postJson('/api/roles', [
            'name' => 'Staf Kontak',
            'permissions' => ['contacts.partner.view'],
        ])->assertCreated()->assertJsonPath('data.permissions', ['contacts.partner.view'])->json('data.id');

        $user = User::factory()->create();
        $user->assignRole('Staf Kontak');
        $this->actingAs($user);

        $this->getJson('/api/contacts/partners')->assertOk();
        $this->postJson('/api/contacts/partners', ['type' => 'company', 'name' => 'X'])->assertForbidden();

        $this->admin();
        $this->putJson("/api/roles/{$roleId}", [
            'name' => 'Staf Kontak',
            'permissions' => ['contacts.partner.view', 'contacts.partner.manage'],
        ])->assertOk()->assertJsonPath('data.permissions_count', 2);
    }

    public function test_list_roles_with_counts(): void
    {
        $this->admin();
        $role = Role::create(['name' => 'Kasir', 'guard_name' => 'web']);
        User::factory()->count(2)->create()->each->assignRole($role);

        $roles = collect($this->getJson('/api/roles')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(2, $roles['Kasir']['users_count']);
        $this->assertSame(1, $roles['super-admin']['users_count']);
        $this->assertTrue($roles['super-admin']['is_system']);
        $this->getJson("/api/roles/{$role->id}")->assertOk()->assertJsonPath('data.users_count', 2);
    }

    public function test_permissions_are_grouped_by_enabled_module(): void
    {
        $this->admin();

        $modules = collect($this->getJson('/api/permissions')->assertOk()->json('data'))->pluck('module');
        $this->assertEquals(['Base'], $modules->all());

        $this->enableModules('Product');
        $modules = collect($this->getJson('/api/permissions')->json('data'))->pluck('module');
        $this->assertEquals(['Base', 'Product'], $modules->all());
    }

    public function test_super_admin_role_is_protected(): void
    {
        $this->admin();
        $role = Role::findByName('super-admin', 'web');

        $this->putJson("/api/roles/{$role->id}", ['name' => 'boss'])->assertUnprocessable();
        $this->deleteJson("/api/roles/{$role->id}")->assertUnprocessable();
    }

    public function test_role_name_must_be_unique_and_permissions_valid(): void
    {
        $this->admin();

        $this->postJson('/api/roles', ['name' => 'super-admin'])->assertJsonValidationErrors('name');
        $this->postJson('/api/roles', ['name' => 'A', 'permissions' => ['tidak.ada']])
            ->assertJsonValidationErrors('permissions.0');
    }

    public function test_delete_role(): void
    {
        $this->admin();
        $role = Role::create(['name' => 'Sementara', 'guard_name' => 'web']);

        $this->deleteJson("/api/roles/{$role->id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
