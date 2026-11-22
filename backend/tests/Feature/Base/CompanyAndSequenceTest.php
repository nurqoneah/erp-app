<?php

namespace Tests\Feature\Base;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Base\Models\Sequence;
use Modules\Base\Services\SequenceService;
use Tests\TestCase;

class CompanyAndSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_update_company_profile(): void
    {
        $this->admin();

        $this->getJson('/api/company')->assertOk()->assertJsonPath('data.currency', 'IDR');

        $this->putJson('/api/company', [
            'name' => 'PT Maju Jaya',
            'tax_id' => '01.234.567.8-901.000',
            'country' => 'Indonesia',
            'currency' => 'IDR',
            'email' => 'bukan-email',
        ])->assertJsonValidationErrors('email');

        $this->putJson('/api/company', [
            'name' => 'PT Maju Jaya',
            'tax_id' => '01.234.567.8-901.000',
            'country' => 'Indonesia',
            'currency' => 'IDR',
        ])->assertOk()->assertJsonPath('data.name', 'PT Maju Jaya');

        $this->assertDatabaseCount('companies', 1);
    }

    public function test_enabling_module_creates_its_sequences(): void
    {
        $this->admin();
        $this->assertDatabaseMissing('sequences', ['code' => 'sales.order']);

        $this->installModules('Sales');

        $sequences = collect($this->getJson('/api/sequences')->assertOk()->json('data'))->keyBy('code');
        $this->assertSame('SO/'.now()->year.'/0001', $sequences['sales.order']['preview']);
        $this->assertTrue($sequences->has('inventory.receipt'));
    }

    public function test_sequence_numbers_increment_and_reset_yearly(): void
    {
        $this->installModules('Sales');
        $service = app(SequenceService::class);

        $this->assertSame('SO/2026/0001', $service->next('sales.order', Carbon::parse('2026-12-30')));
        $this->assertSame('SO/2026/0002', $service->next('sales.order', Carbon::parse('2026-12-31')));
        $this->assertSame('SO/2027/0001', $service->next('sales.order', Carbon::parse('2027-01-01')));
    }

    public function test_monthly_sequence_with_placeholders(): void
    {
        $this->installModules('Pos');
        $service = app(SequenceService::class);

        $this->assertSame('POS/202611/00001', $service->next('pos.order', Carbon::parse('2026-11-05')));
        $this->assertSame('POS/202611/00002', $service->next('pos.order', Carbon::parse('2026-11-20')));
        $this->assertSame('POS/202612/00001', $service->next('pos.order', Carbon::parse('2026-12-01')));
    }

    public function test_update_sequence_settings(): void
    {
        $this->admin();
        $this->installModules('Sales');
        $sequence = Sequence::query()->where('code', 'sales.order')->firstOrFail();

        $this->putJson("/api/sequences/{$sequence->id}", [
            'name' => 'Pesanan',
            'prefix' => 'S{YY}-',
            'suffix' => '',
            'padding' => 3,
            'next_number' => 42,
            'reset' => 'never',
        ])->assertOk()->assertJsonPath('data.preview', 'S'.now()->format('y').'-042');

        $this->putJson("/api/sequences/{$sequence->id}", [
            'name' => 'Pesanan', 'padding' => 3, 'next_number' => 0, 'reset' => 'weekly',
        ])->assertJsonValidationErrors(['next_number', 'reset']);
    }
}
