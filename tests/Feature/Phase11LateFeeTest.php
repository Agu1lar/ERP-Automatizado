<?php

namespace Tests\Feature;

use App\Enums\LateFeeRuleScope;
use App\Enums\ReceivableTitleStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\LateFeeRule;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Enums\AssetStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\LateFeeChargeService;
use App\Support\DelinquencyReportQuery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Finance\DelinquencyReportIndex;
use Tests\TestCase;

class Phase11LateFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_calculates_multa_and_juros_pro_rata(): void
    {
        $customer = $this->customer();
        $title = $this->createTitle($customer, 1000, now()->subDays(60));

        $breakdown = app(LateFeeChargeService::class)->calculate($title, 2.0, 1.0);

        $this->assertSame(1000.0, $breakdown['valor_limpo']);
        $this->assertSame(20.0, $breakdown['multa_valor']);
        $this->assertSame(20.0, $breakdown['juros_valor']);
        $this->assertSame(1040.0, $breakdown['valor_total']);
        $this->assertSame(60, $breakdown['dias_atraso']);
    }

    public function test_rule_resolution_prioritizes_rental_over_customer_over_global(): void
    {
        $customer = $this->customer();
        $asset = $this->asset('PAT-LATE-1', AssetStatus::Disponivel);
        $rental = Rental::create([
            'codigo' => 'LOC-LATE-1',
            'customer_id' => $customer->id,
            'asset_id' => $asset->id,
            'status' => 'ativa',
            'reserved_at' => now(),
        ]);

        LateFeeRule::create([
            'escopo' => LateFeeRuleScope::Global->value,
            'multa_percent' => 1,
            'juros_mensal_percent' => 1,
            'ativo' => true,
        ]);

        LateFeeRule::create([
            'escopo' => LateFeeRuleScope::Customer->value,
            'customer_id' => $customer->id,
            'multa_percent' => 2,
            'juros_mensal_percent' => 2,
            'ativo' => true,
        ]);

        LateFeeRule::create([
            'escopo' => LateFeeRuleScope::Rental->value,
            'rental_id' => $rental->id,
            'multa_percent' => 3,
            'juros_mensal_percent' => 3,
            'ativo' => true,
        ]);

        $title = $this->createTitle($customer, 500, now()->subDays(10), $rental->id);
        $rule = app(LateFeeChargeService::class)->resolveRule($title);

        $this->assertNotNull($rule);
        $this->assertSame('3.0000', (string) $rule->multa_percent);
    }

    public function test_apply_batch_persists_charges_on_titles(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();

        app(LateFeeChargeService::class)->saveRule(LateFeeRuleScope::Global, 2, 1);

        $title = $this->createTitle($customer, 1000, now()->subDays(30));

        $this->actingAs($user);

        $applied = app(LateFeeChargeService::class)->applyBatch(
            now()->subMonths(2),
            now(),
        );

        $this->assertCount(1, $applied);

        $title->refresh();
        $this->assertNotNull($title->encargos_aplicados_em);
        $this->assertSame('20.00', $title->multa_valor);
        $this->assertSame('10.00', $title->juros_valor);
        $this->assertSame('1030.00', $title->valor_total_com_encargos);
    }

    public function test_delinquency_report_shows_charge_breakdown(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();

        app(LateFeeChargeService::class)->saveRule(LateFeeRuleScope::Global, 2, 1);
        $this->createTitle($customer, 1000, now()->subDays(30));

        $this->actingAs($user);

        Livewire::test(DelinquencyReportIndex::class)
            ->assertSee('Total com encargos')
            ->assertSee('Detalhamento por título')
            ->assertSee('Multa R$')
            ->assertSee('1.000,00');
    }

    public function test_gestor_can_open_late_fee_modal(): void
    {
        $user = $this->user(UserRole::Gestor);
        $this->actingAs($user);

        Livewire::test(DelinquencyReportIndex::class)
            ->call('openChargeModal')
            ->assertSet('showChargeModal', true)
            ->set('multa_percent', '2.5')
            ->set('juros_mensal_percent', '1.5')
            ->call('saveLateFeeRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('late_fee_rules', [
            'escopo' => LateFeeRuleScope::Global->value,
            'multa_percent' => 2.5,
            'juros_mensal_percent' => 1.5,
            'ativo' => true,
        ]);
    }

    public function test_charge_summary_aggregates_overdue_titles(): void
    {
        $customer = $this->customer();
        app(LateFeeChargeService::class)->saveRule(LateFeeRuleScope::Global, 2, 1);

        $this->createTitle($customer, 1000, now()->subDays(30));
        $this->createTitle($customer, 500, now()->subDays(15));

        $summary = app(DelinquencyReportQuery::class)->chargeSummary();

        $this->assertSame(1500.0, $summary['valor_limpo']);
        $this->assertGreaterThan(0, $summary['multa_valor']);
        $this->assertGreaterThan(0, $summary['juros_valor']);
        $this->assertGreaterThan($summary['valor_limpo'], $summary['valor_total']);
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
            'nome' => 'Cliente Encargos',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
            'bloqueio_inadimplencia' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Encargos',
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

    private function createTitle(Customer $customer, float $valor, $vencimento, ?int $rentalId = null): ReceivableTitle
    {
        return ReceivableTitle::create([
            'codigo' => 'TIT-'.uniqid(),
            'customer_id' => $customer->id,
            'rental_id' => $rentalId,
            'parcela' => 1,
            'total_parcelas' => 1,
            'valor' => $valor,
            'vencimento' => $vencimento,
            'status' => ReceivableTitleStatus::Aberto->value,
        ]);
    }
}
