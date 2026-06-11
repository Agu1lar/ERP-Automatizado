<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\RentalQuoteStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Rental\RentalQuote;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalQuoteService;
use App\Services\RentalService;
use App\Enums\RentalPricingPeriod;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Rental\QuoteIndex;
use App\Livewire\Yard\AssetYardScan;
use Tests\TestCase;

class Phase12FeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_accounting_export_omie_format(): void
    {
        $user = $this->commercialUser();
        $customer = Customer::create([
            'nome' => 'Cliente Contábil',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        ReceivableTitle::create([
            'codigo' => 'TIT-EXP-001',
            'customer_id' => $customer->id,
            'parcela' => 1,
            'total_parcelas' => 1,
            'valor' => 1500,
            'vencimento' => now()->addDays(10),
            'status' => 'aberto',
        ]);

        $response = $this->actingAs($user)->get(route('finance.accounting.export', ['format' => 'omie', 'status' => 'aberto', 'exclude_exported' => 1]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('codigo_lancamento_integracao', $content);
        $this->assertStringContainsString('TIT-EXP-001', $content);

        $title = ReceivableTitle::query()->where('codigo', 'TIT-EXP-001')->first();
        $this->assertNotNull($title->exportado_erp_em);
        $this->assertSame('omie', $title->exportado_erp_formato);

        $again = $this->actingAs($user)->get(route('finance.accounting.export', ['format' => 'omie', 'status' => 'aberto', 'exclude_exported' => 1]));
        $again->assertOk();
        $this->assertStringNotContainsString('TIT-EXP-001', $again->streamedContent());
    }

    public function test_accounting_export_bling_format(): void
    {
        $user = $this->commercialUser();
        $customer = Customer::create([
            'nome' => 'Construtora BH',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        ReceivableTitle::create([
            'codigo' => 'TIT-BLING-001',
            'customer_id' => $customer->id,
            'parcela' => 1,
            'total_parcelas' => 1,
            'valor' => 850,
            'vencimento' => now()->addDays(15),
            'status' => 'aberto',
        ]);

        ReceivableTitle::create([
            'codigo' => 'TIT-PAID-001',
            'customer_id' => $customer->id,
            'parcela' => 1,
            'total_parcelas' => 1,
            'valor' => 200,
            'vencimento' => now()->subDays(5),
            'status' => 'pago',
        ]);

        $response = $this->actingAs($user)->get(route('finance.accounting.export', ['format' => 'bling', 'status' => 'aberto', 'exclude_exported' => 1]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Data de Vencimento', $content);
        $this->assertStringContainsString('Valor do Documento', $content);
        $this->assertStringContainsString('TIT-BLING-001', $content);
        $this->assertStringNotContainsString('TIT-PAID-001', $content);
        $this->assertStringContainsString('aberto', $content);
        $this->assertStringContainsString('Construtora BH', $content);
    }

    public function test_asset_scan_redirects_operators_to_yard(): void
    {
        $user = $this->operationalUser();
        $asset = $this->asset('PAT-YARD-1');

        $this->actingAs($user)
            ->get(route('assets.scan', $asset->codigo_patrimonio))
            ->assertRedirect(route('yard.scan', $asset->codigo_patrimonio));
    }

    public function test_yard_checkout_with_checklist(): void
    {
        $user = $this->operationalUser();
        $customer = Customer::create([
            'nome' => 'Cliente Pátio',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
        $asset = $this->asset('PAT-YARD-2', AssetStatus::Disponivel);

        $this->actingAs($user);
        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(3));

        Livewire::test(AssetYardScan::class, ['codigo' => $asset->codigo_patrimonio])
            ->set('checklist', [
                'visual_ok' => true,
                'acessorios_ok' => true,
                'identificacao_ok' => true,
            ])
            ->call('submitCheckout')
            ->assertHasNoErrors();

        $this->assertSame('locado', $rental->fresh()->status);
    }

    public function test_quote_convert_to_reservation(): void
    {
        $user = $this->commercialUser();
        $customer = Customer::create([
            'nome' => 'Cliente Orçamento',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);
        $asset = $this->asset('PAT-ORC-1', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 120,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $quote = app(RentalQuoteService::class)->create(
            $asset,
            $customer,
            now()->addDays(5),
            'Obra Centro',
        );
        $quote = app(RentalQuoteService::class)->send($quote, 7);

        $rental = app(RentalQuoteService::class)->convertToReservation($quote);

        $this->assertSame(RentalQuoteStatus::Convertido->value, $quote->fresh()->status);
        $this->assertSame($rental->id, $quote->fresh()->rental_id);
        $this->assertSame('reservado', $rental->status);
    }

    public function test_quote_expires_after_validity(): void
    {
        $user = $this->commercialUser();
        $customer = Customer::create(['nome' => 'C', 'cpf_cnpj' => '39053344705', 'ativo' => true]);
        $asset = $this->asset('PAT-ORC-2');

        $this->actingAs($user);

        $quote = RentalQuote::create([
            'codigo' => 'ORC-000001',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalQuoteStatus::Enviado->value,
            'valid_until' => now()->subDay(),
            'sent_at' => now()->subDays(8),
        ]);

        $count = app(RentalQuoteService::class)->expireDueQuotes();

        $this->assertSame(1, $count);
        $this->assertSame(RentalQuoteStatus::Expirado->value, $quote->fresh()->status);
    }

    public function test_quotes_page_loads(): void
    {
        $this->actingAs($this->commercialUser());

        Livewire::test(QuoteIndex::class)
            ->assertSee('Orçamentos')
            ->assertSee('Novo orçamento');
    }

    private function commercialUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Comercial->value);

        return $user;
    }

    private function operationalUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Operacao->value);

        return $user;
    }

    private function asset(string $code, AssetStatus $status = AssetStatus::Disponivel): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Cat',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'M',
            'modelo' => 'X',
            'ativo' => true,
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus(new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]), $status);
    }
}
