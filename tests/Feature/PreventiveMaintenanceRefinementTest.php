<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Services\PreventiveMaintenanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreventiveMaintenanceRefinementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['maintenance.preventive_warning_hours' => 50]);
    }

    public function test_upcoming_assets_detected_before_due(): void
    {
        [$asset, $rule] = $this->assetWithRule(interval: 250, horimetro: 210);

        $status = app(PreventiveMaintenanceService::class)->statusForAssetRule($asset, $rule);

        $this->assertFalse($status['vencida']);
        $this->assertTrue($status['proxima']);
        $this->assertSame(40.0, $status['proxima_em']);
    }

    public function test_alert_mode_disables_auto_open(): void
    {
        config(['maintenance.preventive_auto_mode' => 'alert']);

        $this->assertFalse(app(PreventiveMaintenanceService::class)->shouldAutoOpenOrders());
    }

    public function test_sucata_assets_are_excluded_from_scan(): void
    {
        [$asset, $rule] = $this->assetWithRule(interval: 100, horimetro: 500);
        $asset->update(['status' => AssetStatus::Sucata->value]);

        $due = app(PreventiveMaintenanceService::class)->dueAssets();

        $this->assertEmpty(array_filter($due, fn ($item) => $item['asset']->id === $asset->id));
    }

    /** @return array{0: Asset, 1: PreventiveMaintenanceRule} */
    private function assetWithRule(float $interval, float $horimetro): array
    {
        $category = EquipmentCategory::create([
            'nome' => 'Prev',
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
            'codigo_patrimonio' => 'PAT-PREV-'.uniqid(),
            'equipment_model_id' => $model->id,
            'horimetro' => $horimetro,
            'localizacao' => 'Pátio',
        ]);

        $asset = app(\App\Services\AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);

        $rule = PreventiveMaintenanceRule::create([
            'equipment_model_id' => $model->id,
            'interval_horas' => $interval,
            'descricao' => 'Revisão geral',
            'ativo' => true,
        ]);

        return [$asset, $rule];
    }
}
