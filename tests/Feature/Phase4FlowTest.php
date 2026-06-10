<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\MaintenanceOrderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4FlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_open_maintenance_order_updates_asset_status(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $order = app(MaintenanceOrderService::class)->open(
            $asset,
            'Motor com ruído anormal',
            MaintenanceOrderType::Corretiva,
        );

        $asset->refresh();

        $this->assertEquals(MaintenanceOrderStatus::Aberta->value, $order->status);
        $this->assertEquals(AssetStatus::EmManutencao->value, $asset->status);
    }

    public function test_full_maintenance_workflow(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $service = app(MaintenanceOrderService::class);
        $order = $service->open($asset, 'Troca de escova');

        $service->start($order);
        $service->addPart($order->fresh(), 'Escova de carvão', 2, 'ESC-01', 45.50);
        $service->addLaborHour($order->fresh(), 'Substituição de escovas', 1.5);

        $order = $service->waitForPart($order->fresh(), 'Aguardando chegada da peça');
        $order->asset->refresh();
        $this->assertEquals(AssetStatus::AguardandoPeca->value, $order->asset->status);

        $order = $service->resume($order->fresh());
        $order = $service->complete($order->fresh(), 'Escovas substituídas e testadas');

        $order->asset->refresh();

        $this->assertEquals(MaintenanceOrderStatus::Concluida->value, $order->status);
        $this->assertEquals(AssetStatus::Disponivel->value, $order->asset->status);
        $this->assertEquals(1, $order->parts()->count());
        $this->assertEquals(1, $order->laborHours()->count());
    }

    public function test_blocking_order_prevents_asset_release_to_disponivel(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $service = app(MaintenanceOrderService::class);
        $order = $service->open($asset, 'Revisão geral');
        $service->start($order->fresh());

        $this->expectException(\InvalidArgumentException::class);
        app(AssetStatusService::class)->transition($asset->fresh(), AssetStatus::Disponivel);
    }

    public function test_cancel_open_order_releases_asset(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $service = app(MaintenanceOrderService::class);
        $order = $service->open($asset, 'Abertura indevida');
        $order = $service->cancel($order, 'Aberta por engano');

        $asset->refresh();

        $this->assertEquals(MaintenanceOrderStatus::Cancelada->value, $order->status);
        $this->assertEquals(AssetStatus::Disponivel->value, $asset->status);
    }

    public function test_cannot_open_two_orders_for_same_asset(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        app(MaintenanceOrderService::class)->open($asset, 'Primeira OS');

        $this->expectException(\InvalidArgumentException::class);
        app(MaintenanceOrderService::class)->open($asset->fresh(), 'Segunda OS');
    }

    public function test_maintenance_index_page_loads(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('maintenance.index'))
            ->assertOk();
    }

    public function test_maintenance_show_page_loads(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $order = app(MaintenanceOrderService::class)->open($asset, 'Teste de exibição');

        $this->actingAs($admin)
            ->get(route('maintenance.show', $order))
            ->assertOk()
            ->assertSee($order->codigo);
    }

    public function test_user_without_role_cannot_manage_maintenance(): void
    {
        $user = User::factory()->create(['ativo' => true]);

        $this->actingAs($user);

        $this->assertFalse($user->can('create', MaintenanceOrder::class));
        $this->assertFalse($user->can('maintenance.manage'));
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
