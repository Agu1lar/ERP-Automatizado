<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Livewire\Customer\CustomerIndex;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\GlobalSearchService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class CustomerBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manual_block_requires_justification(): void
    {
        $user = $this->user(UserRole::Gestor);
        $this->actingAs($user);

        Livewire::test(CustomerIndex::class)
            ->call('create')
            ->set('nome', 'Cliente Bloqueado')
            ->set('cpf_cnpj', '529.982.247-25')
            ->set('bloqueado', true)
            ->call('save')
            ->assertHasErrors(['motivo_bloqueio']);
    }

    public function test_manual_block_prevents_new_rental(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = Customer::create([
            'nome' => 'Inadimplente Manual',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
            'bloqueado' => true,
            'motivo_bloqueio' => 'Parcelas 2 e 3 em atraso · Nome no SPC',
            'bloqueado_at' => now(),
            'bloqueado_by' => $user->id,
        ]);

        $this->actingAs($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nome no SPC');

        app(RentalService::class)->reserve($this->asset('PAT-BLK-1'), $customer, now()->addDays(2));
    }

    public function test_global_search_marks_blocked_customer_in_red_metadata(): void
    {
        Customer::create([
            'nome' => 'Cliente Vermelho Busca',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
            'bloqueado' => true,
            'motivo_bloqueio' => 'Não pagou locação de janeiro/2026',
        ]);

        $results = app(GlobalSearchService::class)->fullResults('Cliente Vermelho Busca');
        $customer = $results['customers']->first();

        $this->assertNotNull($customer);
        $this->assertTrue($customer['blocked']);
        $this->assertStringContainsString('janeiro/2026', $customer['block_reason']);
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function asset(string $code): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Cat',
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
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
