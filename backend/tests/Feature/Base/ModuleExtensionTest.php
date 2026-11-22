<?php

namespace Tests\Feature\Base;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Contacts\Models\Partner;
use Tests\TestCase;

/**
 * Modul contoh "Membership" memperluas Kontak tanpa mengubah kode modul Kontak.
 */
class ModuleExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_extension_fields_are_absent_until_module_is_installed(): void
    {
        $this->admin();
        $this->installModules('Contacts');
        $partner = Partner::factory()->create();

        $this->getJson("/api/contacts/partners/{$partner->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.member_number');
        $this->assertFalse(Schema::hasColumn('partners', 'member_number'));
    }

    public function test_installed_extension_adds_fields_validation_and_casts(): void
    {
        $this->admin();
        $this->postJson('/api/modules/Membership/install')->assertOk()->assertJsonPath('installed', ['Contacts', 'Membership']);

        $id = $this->postJson('/api/contacts/partners', [
            'type' => 'person',
            'name' => 'Rina',
            'member_number' => 'M-001',
            'member_level' => 'gold',
            'member_since' => '2026-01-15',
        ])->assertCreated()
            ->assertJsonPath('data.member_number', 'M-001')
            ->assertJsonPath('data.member_level', 'gold')
            ->assertJsonPath('data.member_since', '2026-01-15')
            ->json('data.id');

        // Validasi dari modul perluasan.
        $this->postJson('/api/contacts/partners', ['type' => 'person', 'name' => 'Lain', 'member_number' => 'M-001'])
            ->assertJsonValidationErrors(['member_number' => 'Nomor member sudah dipakai.']);
        $this->postJson('/api/contacts/partners', ['type' => 'person', 'name' => 'Lain', 'member_level' => 'diamond'])
            ->assertJsonValidationErrors('member_level');

        // Update kontak yang sama dengan nomor member yang sama tetap boleh.
        $this->putJson("/api/contacts/partners/{$id}", ['type' => 'person', 'name' => 'Rina S', 'member_number' => 'M-001'])
            ->assertOk();
    }

    public function test_uninstalling_extension_restores_original_table(): void
    {
        $this->admin();
        $this->installModules('Membership');
        $partner = Partner::factory()->create(['member_number' => 'M-9']);

        $this->postJson('/api/modules/Membership/uninstall', ['confirm' => true])->assertOk();

        $this->assertFalse(Schema::hasColumn('partners', 'member_number'));
        $this->assertTrue(Schema::hasTable('partners'));
        $this->getJson("/api/contacts/partners/{$partner->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.member_number');
        $this->postJson('/api/contacts/partners', ['type' => 'company', 'name' => 'Biasa'])->assertCreated();
    }
}
