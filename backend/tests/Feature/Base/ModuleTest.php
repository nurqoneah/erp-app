<?php

namespace Tests\Feature\Base;

use App\Core\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Contacts\Models\Partner;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lists_modules_with_only_base_installed_by_default(): void
    {
        $this->admin();

        $modules = collect($this->getJson('/api/modules')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame('installed', $modules['Base']['state']);
        $this->assertSame('uninstalled', $modules['Sales']['state']);
        $this->assertEqualsCanonicalizing(
            ['Base', 'Contacts', 'Product', 'Inventory', 'Sales', 'Purchase', 'Pos', 'Membership'],
            $modules->keys()->all(),
        );
        $this->assertFalse(Schema::hasTable('partners'));
    }

    public function test_install_runs_migrations_and_dependencies(): void
    {
        $this->admin();

        $this->postJson('/api/modules/Pos/install')
            ->assertOk()
            ->assertJsonPath('installed', ['Contacts', 'Product', 'Inventory', 'Pos']);

        $this->assertTrue(Schema::hasTable('partners'));
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertDatabaseHas('sequences', ['code' => 'pos.order']);

        $menu = collect($this->getJson('/api/menu')->assertOk()->json('data'))->pluck('module');
        $this->assertEquals(['Base', 'Contacts', 'Product', 'Inventory', 'Pos'], $menu->all());
    }

    public function test_uninstall_plan_lists_dependents_first(): void
    {
        $this->admin();
        $this->installModules('Sales', 'Purchase');

        $this->getJson('/api/modules/Product/uninstall')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Sales', 'Purchase', 'Inventory', 'Product']);
    }

    public function test_uninstall_requires_confirmation(): void
    {
        $this->admin();
        $this->installModules('Contacts');

        $this->postJson('/api/modules/Contacts/uninstall')->assertJsonValidationErrors('confirm');
        $this->assertTrue(Schema::hasTable('partners'));
    }

    public function test_uninstall_permanently_removes_tables_permissions_and_sequences(): void
    {
        $this->admin();
        $this->installModules('Sales');
        Partner::factory()->create();
        $role = Role::create(['name' => 'Sales', 'guard_name' => 'web']);
        $role->givePermissionTo(['sales.order.view', 'contacts.partner.view']);

        $this->postJson('/api/modules/Contacts/uninstall', ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('uninstalled', ['Sales', 'Contacts']);

        $this->assertFalse(Schema::hasTable('partners'));
        $this->assertDatabaseMissing('sequences', ['code' => 'sales.order']);
        $this->assertDatabaseMissing('permissions', ['name' => 'contacts.partner.view']);
        $this->assertSame([], $role->fresh()->permissions->pluck('name')->all());

        // Inventori & Produk tidak bergantung pada Kontak, jadi tetap terpasang.
        $this->assertTrue(Schema::hasTable('products'));
        $this->getJson('/api/contacts/partners')->assertNotFound();

        // Install ulang: tabel dibuat lagi dalam keadaan kosong.
        $this->postJson('/api/modules/Contacts/install')->assertOk();
        $this->assertSame(0, Partner::query()->count());
    }

    public function test_cannot_uninstall_required_or_uninstalled_module(): void
    {
        $this->admin();

        $this->postJson('/api/modules/Base/uninstall', ['confirm' => true])->assertUnprocessable();
        $this->postJson('/api/modules/Sales/uninstall', ['confirm' => true])->assertUnprocessable();
    }

    public function test_upgrade_runs_new_migrations_and_updates_version(): void
    {
        $this->admin();
        $this->installModules('Contacts');

        // Simulasikan: versi terpasang lebih lama & ada migration yang belum dijalankan.
        DB::table('modules')->where('name', 'Contacts')->update(['installed_version' => '0.0.1']);
        Schema::drop('partners');
        DB::table('migrations')->where('migration', 'like', '%create_partners_table')->delete();

        $contacts = fn () => collect($this->getJson('/api/modules')->json('data'))->firstWhere('name', 'Contacts');
        app(ModuleManager::class)->flush();
        $this->assertSame('to_upgrade', $contacts()['state']);

        $this->postJson('/api/modules/Contacts/upgrade')->assertOk();

        $this->assertTrue(Schema::hasTable('partners'));
        $this->assertSame('installed', $contacts()['state']);
        $this->assertSame('0.1.0', $contacts()['installed_version']);
    }

    public function test_unknown_module_returns_404(): void
    {
        $this->admin();

        $this->postJson('/api/modules/Nope/install')->assertNotFound();
    }

    public function test_user_without_permission_cannot_manage_modules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/modules/Sales/install')->assertForbidden();
        $this->postJson('/api/modules/Sales/uninstall', ['confirm' => true])->assertForbidden();
    }

    public function test_menu_only_shows_items_user_is_allowed_to_see(): void
    {
        $this->installModules('Contacts');
        $this->actingWithPermissions('contacts.partner.view');

        $menu = collect($this->getJson('/api/menu')->assertOk()->json('data'));

        $this->assertEquals(['Contacts'], $menu->pluck('module')->all());
    }
}
