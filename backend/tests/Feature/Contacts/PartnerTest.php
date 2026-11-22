<?php

namespace Tests\Feature\Contacts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contacts\Models\Partner;
use Tests\TestCase;

class PartnerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_routes_are_unavailable_while_module_is_disabled(): void
    {
        $this->admin();

        $this->getJson('/api/contacts/partners')->assertNotFound();
    }

    public function test_crud_company_with_person_contact(): void
    {
        $this->admin();
        $this->installModules('Contacts');

        $companyId = $this->postJson('/api/contacts/partners', [
            'type' => 'company',
            'name' => 'PT Sumber Rejeki',
            'email' => 'info@sumber.co.id',
            'is_customer' => true,
        ])->assertCreated()->assertJsonPath('data.created_at', fn ($v) => $v !== null)->json('data.id');

        $this->postJson('/api/contacts/partners', [
            'type' => 'person',
            'name' => 'Andi',
            'parent_id' => $companyId,
            'job_title' => 'Purchasing',
        ])->assertCreated()->assertJsonPath('data.display_name', 'PT Sumber Rejeki, Andi');

        $this->getJson("/api/contacts/partners/{$companyId}")
            ->assertOk()
            ->assertJsonPath('data.contacts.0.name', 'Andi');

        $this->putJson("/api/contacts/partners/{$companyId}", ['type' => 'person', 'name' => 'Ganti'])
            ->assertStatus(422);

        $this->deleteJson("/api/contacts/partners/{$companyId}")->assertOk();
        $this->assertSoftDeleted('partners', ['id' => $companyId]);
    }

    public function test_person_cannot_be_parent_and_company_cannot_have_parent(): void
    {
        $this->admin();
        $this->installModules('Contacts');
        $person = Partner::factory()->person()->create();
        $company = Partner::factory()->create();

        $this->postJson('/api/contacts/partners', ['type' => 'person', 'name' => 'B', 'parent_id' => $person->id])
            ->assertJsonValidationErrors('parent_id');
        $this->postJson('/api/contacts/partners', ['type' => 'company', 'name' => 'C', 'parent_id' => $company->id])
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_filters(): void
    {
        $this->admin();
        $this->installModules('Contacts');
        Partner::factory()->create(['name' => 'Toko Andalas', 'city' => 'Padang']);
        Partner::factory()->vendor()->create(['name' => 'CV Pemasok']);
        Partner::factory()->create(['name' => 'Lama', 'is_active' => false]);

        $this->getJson('/api/contacts/partners?role=vendor')->assertJsonPath('data.0.name', 'CV Pemasok')->assertJsonCount(1, 'data');
        $this->getJson('/api/contacts/partners?search=padang')->assertJsonPath('data.0.name', 'Toko Andalas');
        $this->getJson('/api/contacts/partners?active=0')->assertJsonPath('data.0.name', 'Lama');
        $this->getJson('/api/contacts/partners?sort=name&direction=desc&per_page=1')
            ->assertJsonPath('data.0.name', 'Toko Andalas')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_search_escapes_wildcards(): void
    {
        $this->admin();
        $this->installModules('Contacts');
        Partner::factory()->create(['name' => 'Diskon 100%']);
        Partner::factory()->create(['name' => 'Biasa']);

        $this->getJson('/api/contacts/partners?search='.urlencode('%'))->assertJsonCount(1, 'data');
    }
}
