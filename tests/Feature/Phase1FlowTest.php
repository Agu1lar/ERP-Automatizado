<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Attachment\Attachment;
use App\Models\Domain\Audit\AuditLog;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\AttachmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Phase1FlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_comercial_cannot_access_user_administration(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Comercial->value);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_create_asset_sets_disponivel_status_and_history(): void
    {
        $admin = $this->adminUser();
        $model = $this->createEquipmentModel();

        $this->actingAs($admin);

        $asset = new Asset([
            'codigo_patrimonio' => 'PAT-001',
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio A',
        ]);

        app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);

        $asset->refresh();

        $this->assertEquals(AssetStatus::Disponivel->value, $asset->status);
        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $asset->id,
            'status_novo' => AssetStatus::Disponivel->value,
        ]);
    }

    public function test_valid_status_transition_disponivel_to_bloqueado(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        app(AssetStatusService::class)->transition(
            $asset,
            AssetStatus::Bloqueado,
            'Equipamento com defeito',
        );

        $asset->refresh();

        $this->assertEquals(AssetStatus::Bloqueado->value, $asset->status);
        $this->assertEquals('Equipamento com defeito', $asset->motivo_bloqueio);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Bloqueado, 'Teste');

        $this->actingAs($admin);

        $this->expectException(\InvalidArgumentException::class);

        app(AssetStatusService::class)->transition($asset, AssetStatus::Locado);
    }

    public function test_attachment_upload_creates_storage_and_database_record(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);
        $file = UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf');

        $this->actingAs($admin);

        $attachment = app(AttachmentService::class)->store($asset, $file);

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertDatabaseHas('attachments', [
            'attachable_id' => $asset->id,
            'nome_original' => 'manual.pdf',
        ]);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_asset_update_generates_audit_log(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin);

        $asset->update(['localizacao' => 'Galpão B']);

        $this->assertDatabaseHas('audit_logs', [
            'entidade' => 'Asset',
            'entidade_id' => $asset->id,
            'acao' => 'updated',
        ]);
    }

    public function test_dashboard_is_accessible_for_authenticated_user(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_asset_show_page_loads(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($admin)
            ->get(route('assets.show', $asset))
            ->assertOk();
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

    private function createAsset(AssetStatus $status, ?string $motivo = null): Asset
    {
        $model = $this->createEquipmentModel();

        $asset = new Asset([
            'codigo_patrimonio' => 'PAT-'.uniqid(),
            'equipment_model_id' => $model->id,
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status, $motivo);
    }
}
