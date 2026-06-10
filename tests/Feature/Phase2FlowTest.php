<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\QrCodeStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateAssetQrCodeJob;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\AssetMovement;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetMovementService;
use App\Services\AssetStatusService;
use App\Services\QrCodeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2FlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_asset_creation_dispatches_qr_code_job(): void
    {
        Queue::fake();

        $admin = $this->adminUser();
        $model = $this->createEquipmentModel();

        $this->actingAs($admin);

        $asset = new Asset([
            'codigo_patrimonio' => 'PAT-QR-001',
            'equipment_model_id' => $model->id,
        ]);

        app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);

        Queue::assertPushed(GenerateAssetQrCodeJob::class, fn ($job) => $job->assetId === $asset->id);
    }

    public function test_qr_code_job_generates_png_file(): void
    {
        $asset = $this->createAsset('PAT-QR-002');

        (new GenerateAssetQrCodeJob($asset->id))->handle(app(QrCodeService::class));

        $asset->refresh();

        $this->assertEquals(QrCodeStatus::Generated->value, $asset->qr_code_status);
        $this->assertNotNull($asset->qr_code_path);
        Storage::disk('local')->assertExists($asset->qr_code_path);
    }

    public function test_scan_route_redirects_to_asset_ficha(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset('PAT-SCAN-001');

        $this->actingAs($admin)
            ->get(route('assets.scan', $asset->codigo_patrimonio))
            ->assertRedirect(route('assets.show', $asset));
    }

    public function test_location_movement_is_recorded_in_timeline(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset('PAT-MOV-001');
        $asset->update(['localizacao' => 'Pátio A']);

        $this->actingAs($admin);

        app(AssetMovementService::class)->moveLocation($asset, 'Galpão B', 'Realocação de teste');

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'origem' => 'Pátio A',
            'destino' => 'Galpão B',
        ]);

        $this->assertEquals(1, AssetMovement::where('asset_id', $asset->id)->count());
    }

    public function test_print_page_loads_for_authorized_user(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset('PAT-PRINT-001');

        $this->actingAs($admin)
            ->get(route('assets.print', $asset))
            ->assertOk()
            ->assertSee($asset->codigo_patrimonio);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function createEquipmentModel(): EquipmentModel
    {
        $category = EquipmentCategory::create([
            'nome' => 'Teste',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        return EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca X',
            'modelo' => 'Modelo Y',
            'ativo' => true,
        ]);
    }

    private function createAsset(string $codigo): Asset
    {
        $model = $this->createEquipmentModel();

        $asset = new Asset([
            'codigo_patrimonio' => $codigo,
            'equipment_model_id' => $model->id,
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
