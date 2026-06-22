<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CompanyType;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\PartPurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Person\Company;
use App\Models\User;
use App\Services\MaintenanceOrderService;
use App\Services\PartPurchaseOrderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_purchase_order_receive_increases_stock_and_records_supplier_price(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        $supplier = Company::create([
            'nome' => 'Fornecedor Peças BH',
            'tipo' => CompanyType::Fornecedor->value,
            'ativo' => true,
        ]);

        $part = PartCatalogItem::create([
            'codigo_peca' => 'FIL-PC-01',
            'descricao' => 'Filtro de ar',
            'estoque_atual' => 2,
            'estoque_minimo' => 10,
            'valor_unitario_padrao' => 45,
            'ativo' => true,
        ]);

        $service = app(PartPurchaseOrderService::class);
        $order = $service->create($supplier, [[
            'part_catalog_item_id' => $part->id,
            'quantidade' => 8,
            'valor_unitario' => 42.5,
        ]]);

        $service->markSent($order);
        $service->receive($order->fresh());

        $part->refresh();
        $order->refresh();

        $this->assertSame(10.0, (float) $part->estoque_atual);
        $this->assertSame(PartPurchaseOrderStatus::Recebido->value, $order->status);

        $this->assertDatabaseHas('part_catalog_supplier_prices', [
            'part_catalog_item_id' => $part->id,
            'company_id' => $supplier->id,
            'valor_unitario' => 42.5,
        ]);
    }

    public function test_create_from_low_stock_builds_purchase_order(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        $supplier = Company::create([
            'nome' => 'Auto Peças',
            'tipo' => CompanyType::Fornecedor->value,
            'ativo' => true,
        ]);

        PartCatalogItem::create([
            'codigo_peca' => 'BAIXO-01',
            'descricao' => 'Peça crítica',
            'estoque_atual' => 1,
            'estoque_minimo' => 5,
            'ativo' => true,
        ]);

        $order = app(PartPurchaseOrderService::class)->createFromLowStock($supplier);

        $this->assertSame(1, $order->items()->count());
        $this->assertSame(4.0, (float) $order->items()->first()->quantidade_pedida);
    }

    public function test_purchase_orders_page_renders_with_low_stock_banner(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        Company::create([
            'nome' => 'Fornecedor Teste',
            'tipo' => CompanyType::Fornecedor->value,
            'ativo' => true,
        ]);

        PartCatalogItem::create([
            'codigo_peca' => 'BAIXO-UI',
            'descricao' => 'Peça abaixo do mínimo',
            'estoque_atual' => 0,
            'estoque_minimo' => 3,
            'ativo' => true,
        ]);

        $this->get(route('maintenance.purchase-orders.index'))
            ->assertOk()
            ->assertSee('Pedidos de compra')
            ->assertSee('abaixo do estoque mínimo')
            ->assertSee('Cadastrar fornecedor');
    }

    public function test_indemnity_order_completion_creates_receivable_title(): void
    {
        $admin = $this->adminUser();
        $asset = $this->asset();
        $customer = \App\Models\Domain\Customer\Customer::create([
            'nome' => 'Cliente Indenização',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        $this->actingAs($admin);

        $service = app(MaintenanceOrderService::class);
        $order = $service->open(
            $asset,
            'Equipamento danificado irreparável',
            MaintenanceOrderType::Indenizacao,
        );

        $service->updateTechnicalData(
            $order,
            customerId: $customer->id,
            valorIndenizacao: 1250.00,
        );

        $service->start($order->fresh());
        $order = $service->complete($order->fresh(), 'Cobrança de indenização registrada');

        $order->refresh();

        $this->assertSame(MaintenanceOrderStatus::Concluida->value, $order->status);
        $this->assertNotNull($order->receivable_title_id);

        $this->assertDatabaseHas('receivable_titles', [
            'id' => $order->receivable_title_id,
            'customer_id' => $customer->id,
            'maintenance_order_id' => $order->id,
            'valor' => 1250,
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function asset(): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Proc',
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
            'codigo_patrimonio' => 'PAT-PROC-'.uniqid(),
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(\App\Services\AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
