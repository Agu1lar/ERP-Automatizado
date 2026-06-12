<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CompanyType;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\PayableTitleOrigin;
use App\Enums\PayableTitleStatus;
use App\Enums\PartPurchaseOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\Finance\PayableIndex;
use App\Models\Domain\Finance\PayableTitle;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Person\Company;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\MaintenanceOrderService;
use App\Services\PartPurchaseOrderService;
use App\Services\PayableTitleService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PayableTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manual_payable_title_and_mark_paid(): void
    {
        $user = $this->user(UserRole::Gestor);
        $supplier = Company::create([
            'nome' => 'Fornecedor Teste',
            'tipo' => CompanyType::Fornecedor->value,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $title = app(PayableTitleService::class)->createManual(
            $supplier,
            350.50,
            now()->addDays(10),
            PayableTitleOrigin::Manual,
            'Teste manual',
        );

        $this->assertSame(PayableTitleStatus::Aberto->value, $title->status);
        $this->assertStringStartsWith('PAG-', $title->codigo);

        app(PayableTitleService::class)->markAsPaid($title, PaymentMethod::Transferencia);
        $this->assertSame(PayableTitleStatus::Pago->value, $title->fresh()->status);
    }

    public function test_purchase_order_receive_creates_payable(): void
    {
        $user = $this->user(UserRole::Gestor);
        $supplier = Company::create([
            'nome' => 'Peças MG',
            'tipo' => CompanyType::Fornecedor->value,
            'ativo' => true,
        ]);

        $part = PartCatalogItem::create([
            'codigo_peca' => 'PEC-001',
            'descricao' => 'Filtro',
            'estoque_atual' => 0,
            'estoque_minimo' => 5,
            'valor_unitario_padrao' => 25,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $order = app(PartPurchaseOrderService::class)->create($supplier, [[
            'part_catalog_item_id' => $part->id,
            'quantidade' => 4,
            'valor_unitario' => 25,
        ]]);

        app(PartPurchaseOrderService::class)->markSent($order);
        app(PartPurchaseOrderService::class)->receive($order->fresh());

        $payable = PayableTitle::query()->where('part_purchase_order_id', $order->id)->first();
        $this->assertNotNull($payable);
        $this->assertSame('100.00', $payable->valor);
        $this->assertSame(PayableTitleOrigin::FornecedorPecas->value, $payable->origem);
    }

    public function test_maintenance_complete_creates_external_workshop_payable(): void
    {
        $user = $this->user(UserRole::Gestor);
        $workshop = Company::create([
            'nome' => 'Oficina Externa BH',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        $asset = $this->asset('PAT-PAY-1');

        $this->actingAs($user);

        $service = app(MaintenanceOrderService::class);
        $order = $service->open($asset, 'Reparo em oficina parceira');
        $service->updateTechnicalData(
            $order,
            externalCompanyId: $workshop->id,
            valorServicoExterno: 480,
            includeExternalFields: true,
        );
        $service->start($order->fresh());
        $service->complete($order->fresh(), 'Serviço concluído na oficina parceira');

        $order->refresh();
        $this->assertNotNull($order->payable_title_id);
        $payable = PayableTitle::find($order->payable_title_id);
        $this->assertSame('480.00', $payable->valor);
        $this->assertSame(PayableTitleOrigin::OficinaExterna->value, $payable->origem);
    }

    public function test_payable_index_lists_titles(): void
    {
        $user = $this->user(UserRole::Gestor);
        $supplier = Company::create([
            'nome' => 'Fornecedor UI',
            'tipo' => CompanyType::Fornecedor->value,
            'ativo' => true,
        ]);

        PayableTitle::create([
            'codigo' => 'PAG-26060001',
            'company_id' => $supplier->id,
            'origem' => PayableTitleOrigin::Manual->value,
            'valor' => 200,
            'vencimento' => now()->addDays(5),
            'status' => PayableTitleStatus::Aberto->value,
        ]);

        $this->actingAs($user);

        Livewire::test(PayableIndex::class)
            ->assertSee('PAG-26060001')
            ->assertSee('Fornecedor UI');
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function asset(string $code): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Cat Pay',
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

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
