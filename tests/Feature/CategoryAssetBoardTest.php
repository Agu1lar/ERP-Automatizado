<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Support\CategoryAssetBoard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryAssetBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_category_show_groups_assets_by_operational_status(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Comercial->value);

        $category = EquipmentCategory::create([
            'nome' => 'Betoneiras',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Menegotti',
            'modelo' => '400L',
            'ativo' => true,
        ]);

        $disponivel = $this->createAsset($model, 'PAT-DISP', AssetStatus::Disponivel);
        $locado = $this->createAsset($model, 'PAT-LOC', AssetStatus::Locado);
        $manutencao = $this->createAsset($model, 'PAT-MAN', AssetStatus::EmManutencao);

        $board = CategoryAssetBoard::forCategory($category);

        $this->assertCount(1, $board['disponivel']);
        $this->assertCount(1, $board['locado']);
        $this->assertCount(1, $board['manutencao']);
        $this->assertTrue($board['disponivel']->contains('id', $disponivel->id));
        $this->assertTrue($board['locado']->contains('id', $locado->id));
        $this->assertTrue($board['manutencao']->contains('id', $manutencao->id));

        Livewire::actingAs($user)
            ->test(\App\Livewire\Fleet\CategoryShow::class, ['category' => $category])
            ->assertSee('Betoneiras')
            ->assertSee('PAT-DISP')
            ->assertSee('PAT-LOC')
            ->assertSee('PAT-MAN')
            ->assertSee('Abrir ficha');
    }

    public function test_category_index_links_to_asset_board(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);

        $category = EquipmentCategory::create([
            'nome' => 'Marteletes',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $this->actingAs($user)
            ->get(route('fleet.categories.show', $category))
            ->assertOk()
            ->assertSee('Marteletes');
    }

    private function createAsset(EquipmentModel $model, string $code, AssetStatus $status): Asset
    {
        $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
