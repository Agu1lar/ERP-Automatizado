<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InlineFieldEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_operational_user_can_inline_edit_asset_ficha_on_resumo(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Comercial->value);

        $asset = $this->createAsset();

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Fleet\AssetShow::class, ['asset' => $asset])
            ->assertSee('Clique em qualquer campo para editar')
            ->set('ficha_descricao', 'Betoneira revisada')
            ->call('saveFicha')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'descricao' => 'Betoneira revisada',
        ]);
    }

    public function test_asset_show_no_longer_has_separate_ficha_tab(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Operacao->value);
        $asset = $this->createAsset();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Fleet\AssetShow::class, ['asset' => $asset])
            ->assertSee('Ficha do patrimônio')
            ->assertDontSee('>Ficha<', false);
    }

    private function createAsset(): Asset
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
            'codigo_patrimonio' => 'PAT-INLINE',
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
