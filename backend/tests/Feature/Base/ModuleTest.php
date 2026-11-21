<?php

namespace Tests\Feature\Base;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function actingAsAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_lists_modules_with_base_enabled_by_default(): void
    {
        $this->actingAsAdmin();

        $modules = collect($this->getJson('/api/modules')->assertOk()->json('data'))->keyBy('name');

        $this->assertTrue($modules['Base']['enabled']);
        $this->assertFalse($modules['Sales']['enabled']);
        $this->assertEqualsCanonicalizing(
            ['Base', 'Contacts', 'Product', 'Inventory', 'Sales', 'Purchase', 'Pos'],
            $modules->keys()->all(),
        );
    }

    public function test_enabling_a_module_enables_its_dependencies(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/modules/Pos/enable')
            ->assertOk()
            ->assertJsonPath('activated', ['Contacts', 'Product', 'Inventory', 'Pos']);

        $menu = collect($this->getJson('/api/menu')->assertOk()->json('data'))->pluck('module');
        $this->assertEquals(['Base', 'Contacts', 'Product', 'Inventory', 'Pos'], $menu->all());
    }

    public function test_cannot_disable_module_that_others_depend_on(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/modules/sales/enable')->assertOk();

        $this->postJson('/api/modules/Inventory/disable')->assertUnprocessable();
        $this->postJson('/api/modules/Sales/disable')->assertOk();
        $this->postJson('/api/modules/Inventory/disable')->assertOk();
    }

    public function test_cannot_disable_required_module(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/modules/Base/disable')->assertUnprocessable();
    }

    public function test_unknown_module_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/modules/Nope/enable')->assertNotFound();
    }

    public function test_user_without_permission_cannot_manage_modules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/modules/Sales/enable')->assertForbidden();
    }

    public function test_menu_only_shows_items_user_is_allowed_to_see(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/modules/Contacts/enable')->assertOk();

        $user = User::factory()->create();
        $user->givePermissionTo('contacts.partner.view');
        Sanctum::actingAs($user);

        $menu = collect($this->getJson('/api/menu')->assertOk()->json('data'));

        $this->assertEquals(['Contacts'], $menu->pluck('module')->all());
    }
}
