<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\DocumentPdfService;
use App\Services\MaintenanceOrderService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5FlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_maintenance_order_pdf_download(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $order = app(MaintenanceOrderService::class)->open(
            $asset,
            'Teste PDF OS',
            MaintenanceOrderType::Corretiva,
        );

        $response = $this->get(route('maintenance.pdf', $order));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_rental_pdf_download(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();

        $this->actingAs($admin)
            ->get(route('rentals.pdf', $rental))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_asset_pdf_download(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin)
            ->get(route('assets.pdf', $asset))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_document_pdf_service_generates_maintenance_order(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $order = app(MaintenanceOrderService::class)->open($asset, 'Conteúdo do PDF');

        $pdf = app(DocumentPdfService::class)->maintenanceOrder($order);

        $this->assertNotEmpty($pdf->output());
    }

    public function test_user_without_permission_cannot_download_maintenance_pdf(): void
    {
        $unauthorized = User::factory()->create(['ativo' => true]);

        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);
        $order = app(MaintenanceOrderService::class)->open($asset, 'Teste permissão');

        $this->actingAs($unauthorized)
            ->get(route('maintenance.pdf', $order))
            ->assertForbidden();
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

    private function createReservedRental()
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        $customer = \App\Models\Domain\Customer\Customer::create([
            'nome' => 'Cliente PDF',
            'cpf_cnpj' => '12345678909',
            'ativo' => true,
        ]);

        return app(RentalService::class)->reserve(
            $this->createAsset(AssetStatus::Disponivel),
            $customer,
        );
    }
}
