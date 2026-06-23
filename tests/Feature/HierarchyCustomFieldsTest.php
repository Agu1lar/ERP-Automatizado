<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Enums\CustomFieldEntity;
use App\Enums\UserRole;
use App\Models\Domain\Audit\AuditLog;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\CustomFieldService;
use App\Services\MaintenanceOrderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class HierarchyCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manutencao_can_operate_but_not_create_maintenance_order(): void
    {
        $user = $this->userWithRole(UserRole::Manutencao);
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($this->adminUser());
        $order = app(MaintenanceOrderService::class)->open($asset, 'Teste hierarquia');

        $this->actingAs($user);

        $this->assertFalse($user->can('create', MaintenanceOrder::class));
        $this->assertTrue($user->can('operate', $order));
        $this->assertTrue($user->can('update', $order));
    }

    public function test_gestor_can_create_custom_fields_and_hide_for_self(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($gestor);

        $this->assertTrue($gestor->can('custom_fields.manage'));
        $this->assertTrue($gestor->can('custom_fields.hide'));
        $this->assertTrue($gestor->can('dashboard.analytics'));

        $definition = app(CustomFieldService::class)->createDefinition(
            CustomFieldEntity::Asset,
            'Campo teste',
            \App\Enums\CustomFieldType::Text,
        );

        app(CustomFieldService::class)->toggleHiddenForUser($definition, $gestor, true);

        $visible = app(CustomFieldService::class)->visibleDefinitions(CustomFieldEntity::Asset, $gestor);
        $this->assertFalse($visible->contains('id', $definition->id));
    }

    public function test_comercial_can_manage_customers_but_not_create_fields_or_os(): void
    {
        $comercial = $this->userWithRole(UserRole::Comercial);

        $this->actingAs($comercial);

        $this->assertTrue($comercial->can('customers.manage'));
        $this->assertTrue($comercial->can('records.edit'));
        $this->assertFalse($comercial->can('custom_fields.manage'));
        $this->assertFalse($comercial->can('custom_fields.hide'));
        $this->assertFalse($comercial->can('create', MaintenanceOrder::class));
    }

    public function test_comercial_can_fill_existing_custom_field_but_not_create_definition(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $comercial = $this->userWithRole(UserRole::Comercial);
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($gestor);
        $definition = app(CustomFieldService::class)->createDefinition(
            CustomFieldEntity::Asset,
            'Valor do patrimônio',
            \App\Enums\CustomFieldType::Number,
        );

        $this->actingAs($comercial);

        Livewire::test(\App\Livewire\CustomField\CustomFieldPanel::class, [
            'entityType' => 'asset',
            'entityId' => $asset->id,
        ])
            ->set('new_label', 'Campo novo comercial')
            ->call('createField')
            ->assertForbidden();

        Livewire::test(\App\Livewire\CustomField\CustomFieldPanel::class, [
            'entityType' => 'asset',
            'entityId' => $asset->id,
        ])
            ->set('customFields.'.$definition->field_key, '15000')
            ->call('saveValues')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $definition->id,
            'entity_id' => $asset->id,
            'value' => '15000',
        ]);
    }

    public function test_operational_roles_share_same_permissions(): void
    {
        foreach ([UserRole::Comercial, UserRole::Operacao, UserRole::Manutencao] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user);

            $this->assertTrue($user->can('customers.manage'), $role->value);
            $this->assertTrue($user->can('rentals.reserve'), $role->value);
            $this->assertTrue($user->can('rentals.operate'), $role->value);
            $this->assertTrue($user->can('maintenance.operate'), $role->value);
            $this->assertTrue($user->can('records.edit'), $role->value);
            $this->assertTrue($user->can('dashboard.analytics'), $role->value);
            $this->assertFalse($user->can('custom_fields.manage'), $role->value);
            $this->assertFalse($user->can('maintenance.manage'), $role->value);
            $this->assertFalse($user->can('create', MaintenanceOrder::class), $role->value);
        }
    }

    public function test_custom_field_panel_create_requires_manage_permission(): void
    {
        $asset = $this->createAsset(AssetStatus::Disponivel);
        $operacao = $this->userWithRole(UserRole::Operacao);

        $this->actingAs($operacao);

        Livewire::test(\App\Livewire\CustomField\CustomFieldPanel::class, [
            'entityType' => 'asset',
            'entityId' => $asset->id,
        ])
            ->set('new_label', 'Campo operação')
            ->call('createField')
            ->assertForbidden();

        $this->assertDatabaseMissing('custom_field_definitions', [
            'label' => 'Campo operação',
        ]);
    }

    public function test_gestor_creating_field_is_logged_in_audit(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($gestor);

        $definition = app(CustomFieldService::class)->createDefinition(
            CustomFieldEntity::Asset,
            'Valor do patrimônio',
            \App\Enums\CustomFieldType::Number,
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $gestor->id,
            'entidade' => 'CustomFieldDefinition',
            'entidade_id' => $definition->id,
            'acao' => AuditAction::Created->value,
        ]);

        $log = AuditLog::query()
            ->where('entidade', 'CustomFieldDefinition')
            ->where('entidade_id', $definition->id)
            ->where('acao', AuditAction::Created->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Valor do patrimônio', $log->depois_json['label']);
        $this->assertEquals('asset', $log->depois_json['entity_type']);
    }

    public function test_comercial_filling_custom_field_is_logged_on_parent_entity(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $comercial = $this->userWithRole(UserRole::Comercial);
        $asset = $this->createAsset(AssetStatus::Disponivel);

        $this->actingAs($gestor);
        $definition = app(CustomFieldService::class)->createDefinition(
            CustomFieldEntity::Asset,
            'Valor do patrimônio',
            \App\Enums\CustomFieldType::Number,
        );

        $this->actingAs($comercial);
        app(CustomFieldService::class)->saveValues(
            CustomFieldEntity::Asset,
            $asset->id,
            [$definition->field_key => '25000'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $comercial->id,
            'entidade' => 'Asset',
            'entidade_id' => $asset->id,
            'acao' => AuditAction::Updated->value,
        ]);

        $log = AuditLog::query()
            ->where('entidade', 'Asset')
            ->where('entidade_id', $asset->id)
            ->where('user_id', $comercial->id)
            ->latest()
            ->first();

        $this->assertEquals('25000', $log->depois_json['custom_fields']['Valor do patrimônio']);
    }

    public function test_gestor_can_create_field_via_livewire(): void
    {
        $asset = $this->createAsset(AssetStatus::Disponivel);
        $gestor = $this->userWithRole(UserRole::Gestor);

        $this->actingAs($gestor);

        Livewire::test(\App\Livewire\CustomField\CustomFieldPanel::class, [
            'entityType' => 'asset',
            'entityId' => $asset->id,
        ])
            ->set('showCreateForm', true)
            ->set('new_label', 'Observação interna')
            ->set('new_type', 'text')
            ->call('createField')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('custom_field_definitions', [
            'entity_type' => CustomFieldEntity::Asset->value,
            'label' => 'Observação interna',
        ]);
    }

    private function adminUser(): User
    {
        return $this->userWithRole(UserRole::Admin);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

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
