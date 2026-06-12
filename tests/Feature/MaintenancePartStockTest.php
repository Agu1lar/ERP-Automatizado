<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\PartStockMovementType;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Maintenance\PartStockMovement;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\MaintenanceOrderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePartStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_completing_order_deducts_catalog_stock(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $catalog = PartCatalogItem::create([
            'codigo_peca' => 'ESC-01',
            'descricao' => 'Escova de carvão',
            'valor_unitario_padrao' => 45.50,
            'estoque_atual' => 10,
            'ativo' => true,
        ]);

        $this->actingAs($admin);

        $service = app(MaintenanceOrderService::class);
        $order = $service->open($asset, 'Troca de escova');
        $service->start($order);
        $part = $service->addPart($order->fresh(), 'Escova de carvão', 2, 'ESC-01');

        $this->assertSame($catalog->id, $part->part_catalog_item_id);

        $service->complete($order->fresh(), 'Escovas substituídas');

        $catalog->refresh();
        $part->refresh();

        $this->assertSame(8.0, (float) $catalog->estoque_atual);
        $this->assertTrue($part->estoque_baixado);

        $movement = PartStockMovement::query()
            ->where('maintenance_order_id', $order->id)
            ->where('tipo', PartStockMovementType::SaidaOs->value)
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame(10.0, (float) $movement->saldo_anterior);
        $this->assertSame(8.0, (float) $movement->saldo_posterior);
        $this->assertSame(2.0, (float) $movement->quantidade);
    }

    public function test_completing_order_without_catalog_part_does_not_touch_stock(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $service = app(MaintenanceOrderService::class);
        $order = $service->open($asset, 'Peça avulsa');
        $service->start($order);
        $service->addPart($order->fresh(), 'Peça sem catálogo', 1, 'AVULSA-99');
        $service->complete($order->fresh(), 'Concluída');

        $this->assertSame(0, PartStockMovement::query()->count());
    }

    public function test_insufficient_stock_blocks_order_completion(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        PartCatalogItem::create([
            'codigo_peca' => 'FIL-01',
            'descricao' => 'Filtro',
            'estoque_atual' => 1,
            'ativo' => true,
        ]);

        $this->actingAs($admin);

        $service = app(MaintenanceOrderService::class);
        $order = $service->open($asset, 'Troca de filtro');
        $service->start($order);
        $service->addPart($order->fresh(), 'Filtro', 3, 'FIL-01');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Estoque insuficiente');

        $service->complete($order->fresh(), 'Tentativa inválida');
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function createAsset(AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Teste',
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
            'codigo_patrimonio' => 'PAT-'.uniqid(),
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
