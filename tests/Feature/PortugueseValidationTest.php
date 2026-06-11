<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
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
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use App\Livewire\Maintenance\MaintenanceOrderShow;
use Tests\TestCase;

class PortugueseValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->assertSame('pt_BR', config('app.locale'));
    }

    public function test_validation_messages_are_in_portuguese(): void
    {
        $validator = Validator::make(
            ['labor_descricao' => ''],
            ['labor_descricao' => 'required|string'],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('atividade realizada', $validator->errors()->first('labor_descricao'));
        $this->assertStringNotContainsString('field is required', $validator->errors()->first('labor_descricao'));
    }

    public function test_labor_hour_form_shows_portuguese_validation_error(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);

        $order = $this->openOrder($user);

        Livewire::actingAs($user)
            ->test(MaintenanceOrderShow::class, ['order' => $order])
            ->call('addLaborHour')
            ->assertHasErrors(['labor_descricao'])
            ->assertSee('atividade realizada');
    }

    private function openOrder(User $user): MaintenanceOrder
    {
        $this->actingAs($user);
        $asset = $this->createAsset(AssetStatus::Disponivel);

        return app(MaintenanceOrderService::class)->open(
            $asset,
            'Teste de validação',
            MaintenanceOrderType::Corretiva,
        );
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
            'codigo_patrimonio' => 'PAT-VAL-'.uniqid(),
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
