<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\MaintenanceOrderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class Phase6MaintenancePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_maintenance_pdf_template_includes_os_document_fields(): void
    {
        $admin = $this->adminUser();
        $customer = Customer::create([
            'nome' => 'Cliente OS Teste',
            'cpf_cnpj' => '12345678909',
            'ativo' => true,
        ]);
        $asset = $this->createAsset(AssetStatus::Disponivel, [
            'voltagem' => '220V',
        ]);

        $this->actingAs($admin);

        $order = app(MaintenanceOrderService::class)->open(
            $asset,
            'Motor falhando',
            MaintenanceOrderType::Indenizacao,
        );

        app(MaintenanceOrderService::class)->updateTechnicalData(
            $order,
            parecerTecnico: 'Equipamento substituído conforme acordo.',
            customerId: $customer->id,
            assinaturaCaixa: 'João',
            assinaturaOrcadoPor: 'Maria',
            assinaturaMontadoPor: 'Carlos',
        );

        app(MaintenanceOrderService::class)->addPart(
            $order->fresh(),
            'Motor elétrico',
            1,
            'MOT-01',
            350.00,
            null,
            'ALT-MOT-01',
        );

        $order = $order->fresh(['asset.equipmentModel', 'customer', 'parts']);

        $html = View::make('documents.maintenance-order', [
            'order' => $order,
            'company' => config('documents.company'),
            'logoBase64' => null,
            'generatedAt' => now(),
            'customFieldRows' => [],
        ])->render();

        $this->assertStringContainsString('size: 210mm 99mm', $html);
        $this->assertStringContainsString('ORDEM DE MANUTENÇÃO', $html);
        $this->assertStringContainsString('INDENIZAÇÃO', $html);
        $this->assertStringContainsString('220V', $html);
        $this->assertStringContainsString('Cliente OS Teste', $html);
        $this->assertStringContainsString('PARECER TÉCNICO', $html);
        $this->assertStringContainsString('Equipamento substituído conforme acordo.', $html);
        $this->assertStringContainsString('ALT-MOT-01', $html);
        $this->assertStringContainsString('orçado por: Maria', $html);
    }

    public function test_logo_loads_from_stack_assets_path(): void
    {
        $logoPath = base_path('stack/assets/logo.png');
        $this->assertFileExists($logoPath);

        config(['documents.company.logo_path' => 'stack/assets/logo.png']);

        $service = app(\App\Services\DocumentPdfService::class);
        $reflection = new \ReflectionMethod($service, 'logoBase64');
        $logo = $reflection->invoke($service);

        $this->assertNotNull($logo);
        $this->assertStringStartsWith('data:image/', $logo);
    }

    public function test_maintenance_order_pdf_uses_one_third_a4_height(): void
    {
        $paper = config('documents.paper.maintenance_order');

        $this->assertSame(210, $paper['width_mm']);
        $this->assertSame(99, $paper['height_mm']);
    }

    public function test_maintenance_pdf_route_still_works(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);
        $order = app(MaintenanceOrderService::class)->open($asset, 'Teste rota PDF');

        $this->get(route('maintenance.pdf', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function createAsset(AssetStatus $status, array $overrides = []): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Teste',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'MarcaX',
            'modelo' => 'ModeloY',
            'ativo' => true,
        ]);

        $asset = new Asset(array_merge([
            'codigo_patrimonio' => 'PAT-'.uniqid(),
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ], $overrides));

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
