<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceivableTitleStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Enums\RentalBillingQueueStatus;
use App\Enums\RentalBillingQueueType;
use App\Enums\RentalPricingPeriod;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\ReceivableTitleService;
use App\Services\RentalService;
use App\Support\CashFlowQuery;
use App\Support\DelinquencyReportQuery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Finance\DelinquencyReportIndex;
use App\Livewire\Finance\ReceivableIndex;
use Tests\TestCase;


#[Group('livewire')]
class Phase11FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checkout_creates_billing_queue_entry_instead_of_direct_title(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $asset = $this->asset('PAT-FIN-1', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $this->assertSame(1, ReceivableTitle::query()->where('rental_id', $rental->id)->count());
        $this->assertSame(1, RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->count());

        $entry = RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->first();
        $this->assertSame(RentalBillingQueueType::Locacao->value, $entry->tipo);
        $this->assertSame(RentalBillingQueueStatus::Pendente->value, $entry->status);
        $this->assertSame('600.00', $entry->valor_car);
    }

    public function test_mark_as_paid_updates_title(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $rental = $this->rentalWithValue($customer, 500);

        $title = app(ReceivableTitleService::class)->generateForRental($rental)->first();

        $this->actingAs($user);

        $updated = app(ReceivableTitleService::class)->markAsPaid(
            $title,
            PaymentMethod::Pix,
            'Comprovante recebido',
        );

        $this->assertSame(ReceivableTitleStatus::Pago->value, $updated->status);
        $this->assertSame(PaymentMethod::Pix->value, $updated->forma_pagamento);
    }

    public function test_delinquency_report_groups_aging_buckets(): void
    {
        $customer = $this->customer();
        $this->createTitle($customer, 100, now()->subDays(10));
        $this->createTitle($customer, 200, now()->subDays(45));
        $this->createTitle($customer, 300, now()->subDays(100));

        $summary = app(DelinquencyReportQuery::class)->summary();

        $this->assertSame(600.0, $summary['total_atrasado']);
        $this->assertSame(100.0, $summary['ate_30']);
        $this->assertSame(200.0, $summary['ate_60']);
        $this->assertSame(300.0, $summary['acima_90']);
    }

    public function test_reserve_allowed_for_customer_with_overdue_titles_when_delinquency_block_disabled(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $customer->update(['bloqueio_inadimplencia' => false]);
        $this->createTitle($customer, 150, now()->subDays(5));

        $asset = $this->asset('PAT-OVERDUE', AssetStatus::Disponivel);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(3));

        $this->assertNotNull($rental);
        $this->assertSame($customer->id, $rental->customer_id);
    }

    public function test_reserve_blocked_when_delinquency_block_enabled_and_overdue_titles(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $customer->update(['bloqueio_inadimplencia' => true]);
        $this->createTitle($customer, 150, now()->subDays(5));

        $asset = $this->asset('PAT-OVERDUE-BLOCK', AssetStatus::Disponivel);

        $this->actingAs($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('títulos em atraso');

        app(RentalService::class)->reserve($asset, $customer->fresh(), now()->addDays(3));
    }

    public function test_reserve_blocked_only_when_customer_manually_blocked(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $this->createTitle($customer, 150, now()->subDays(5));
        $customer->update([
            'bloqueado' => true,
            'motivo_bloqueio' => 'Decisão comercial — parcelas em atraso',
            'bloqueado_at' => now(),
            'bloqueado_by' => $user->id,
        ]);

        $asset = $this->asset('PAT-BLOCK', AssetStatus::Disponivel);

        $this->actingAs($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cliente bloqueado');

        app(RentalService::class)->reserve($asset, $customer->fresh(), now()->addDays(3));
    }

    public function test_credit_limit_blocks_reservation(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $customer->update(['limite_credito' => 1000]);

        $this->createTitle($customer, 800, now()->addDays(10));
        $asset = $this->asset('PAT-LIMIT', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 300,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limite de crédito');

        app(RentalService::class)->reserve($asset, $customer, now()->addDays(2));
    }

    public function test_cash_flow_query_sums_open_titles_in_period(): void
    {
        $customer = $this->customer();
        $this->createTitle($customer, 100, now()->addDays(3));
        $this->createTitle($customer, 250, now()->addDays(10));
        $this->createTitle($customer, 400, now()->addDays(40));

        $total = app(CashFlowQuery::class)->totalExpected(now(), now()->addDays(30));

        $this->assertSame(350.0, $total);
    }

    public function test_finance_pages_load_for_gestor(): void
    {
        $gestor = $this->user(UserRole::Gestor);

        $this->actingAs($gestor)
            ->get(route('finance.receivables'))
            ->assertOk();

        Livewire::actingAs($gestor)
            ->test(ReceivableIndex::class)
            ->assertOk();

        Livewire::actingAs($gestor)
            ->test(DelinquencyReportIndex::class)
            ->assertOk();
    }

    public function test_receivable_csv_export(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $this->createTitle($customer, 99, now()->addDays(5));

        $this->actingAs($user)
            ->get(route('finance.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Financeiro',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Financeiro',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);

        $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }

    private function rentalWithValue(Customer $customer, float $valor): Rental
    {
        $asset = $this->asset('PAT-'.uniqid(), AssetStatus::Disponivel);
        $user = $this->user(UserRole::Gestor);
        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(7));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );
        $rental->update(['valor_faturamento' => $valor]);

        return $rental->fresh();
    }

    private function createTitle(Customer $customer, float $valor, $vencimento): ReceivableTitle
    {
        return ReceivableTitle::create([
            'codigo' => 'TIT-'.uniqid(),
            'customer_id' => $customer->id,
            'parcela' => 1,
            'total_parcelas' => 1,
            'valor' => $valor,
            'vencimento' => $vencimento,
            'status' => ReceivableTitleStatus::Aberto->value,
        ]);
    }
}
