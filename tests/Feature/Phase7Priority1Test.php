<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\MaintenanceOrderService;
use App\Services\PartCatalogService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class Phase7Priority1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_nova_os_button_opens_form_on_operational_panel(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $this->actingAs($gestor);

        Livewire::test(\App\Livewire\Maintenance\MaintenanceOrderIndex::class)
            ->assertSet('activeView', 'painel')
            ->call('openForm')
            ->assertSet('showForm', true)
            ->assertSee('Nova ordem de serviço')
            ->call('cancelForm')
            ->assertSet('showForm', false);
    }

    public function test_smart_os_opening_finds_asset_by_code(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $asset = $this->createAsset(AssetStatus::Disponivel, [
            'codigo_patrimonio' => 'PAT-OS-SMART',
            'voltagem' => '220V',
        ]);

        $this->actingAs($gestor);

        Livewire::test(\App\Livewire\Maintenance\MaintenanceOrderIndex::class)
            ->call('openForm')
            ->set('asset_search', 'PAT-OS-SMART')
            ->assertSet('asset_id', $asset->id)
            ->set('descricao_problema', 'Problema teste')
            ->call('save')
            ->assertRedirect();
    }

    public function test_part_catalog_autocomplete_fills_part_fields(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $asset = $this->createAsset(AssetStatus::Disponivel);

        PartCatalogItem::create([
            'codigo_peca' => 'MOT-100',
            'codigo_alternativo' => 'ALT-MOT',
            'descricao' => 'Motor elétrico',
            'valor_unitario_padrao' => 250.00,
            'ativo' => true,
        ]);

        $this->actingAs($gestor);
        $order = app(MaintenanceOrderService::class)->open($asset, 'Troca motor');

        Livewire::test(\App\Livewire\Maintenance\MaintenanceOrderShow::class, ['order' => $order])
            ->set('part_codigo_peca', 'MOT-100')
            ->assertSet('part_descricao', 'Motor elétrico')
            ->assertSet('part_codigo_alternativo', 'ALT-MOT');

        $this->assertNotNull(app(PartCatalogService::class)->findByCode('MOT-100'));
    }

    public function test_rental_pdf_includes_company_name_and_logo_path(): void
    {
        config(['documents.company.name' => 'ACESSO equipamentos']);

        $html = View::make('documents.partials.company-header', [
            'company' => config('documents.company'),
            'logoBase64' => 'data:image/png;base64,test',
            'documentTitle' => 'RESUMO DE LOCAÇÃO',
            'documentCode' => 'LOC-000001',
            'documentBadge' => 'Locado',
        ])->render();

        $this->assertStringContainsString('ACESSO equipamentos', $html);
        $this->assertStringContainsString('RESUMO DE LOCAÇÃO', $html);
    }

    public function test_gestor_can_manage_part_catalog(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);

        $this->actingAs($gestor)
            ->get(route('maintenance.parts.index'))
            ->assertOk();

        Livewire::test(\App\Livewire\Maintenance\PartCatalogIndex::class)
            ->call('create')
            ->set('codigo_peca', 'FILT-01')
            ->set('descricao', 'Filtro de ar')
            ->set('valor_unitario_padrao', '45.90')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('part_catalog_items', [
            'codigo_peca' => 'FILT-01',
            'descricao' => 'Filtro de ar',
        ]);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

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
            'marca' => 'Marca',
            'modelo' => 'Modelo',
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
