<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Livewire\Layout\GlobalSearch;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_global_search_finds_asset_without_accents_and_limits_results(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        $category = EquipmentCategory::create(['nome' => 'Martelete', 'tipo_linha' => 'linha_leve', 'ativo' => true]);
        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Bosch',
            'modelo' => 'GBH 2-24',
            'ativo' => true,
        ]);

        $asset = new Asset([
            'codigo_patrimonio' => 'PAT-DEMO-001',
            'equipment_model_id' => $model->id,
            'serie' => 'SN-2024-001',
        ]);
        app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);

        $component = Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', 'martelete')
            ->set('open', true);

        $suggestions = $component->viewData('suggestions');

        $this->assertGreaterThanOrEqual(1, $suggestions->count());
        $this->assertLessThanOrEqual(5, $suggestions->count());
        $this->assertTrue($suggestions->contains(fn ($s) => $s['label'] === 'PAT-DEMO-001'));
    }
}
