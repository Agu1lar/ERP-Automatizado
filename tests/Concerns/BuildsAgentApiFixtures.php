<?php

namespace Tests\Concerns;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalService;

trait BuildsAgentApiFixtures
{
    protected function agentUser(): User
    {
        $user = $this->user(UserRole::Gestor);
        $user->givePermissionTo('agent.api');

        return $user;
    }

    protected function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    protected function customer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Agente',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);
    }

    protected function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Agente',
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

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }

    protected function reservedRental(): Rental
    {
        $user = $this->agentUser();
        $this->actingAs($user);

        $customer = $this->customer();
        $asset = $this->asset('PAT-AGENT-CTX', AssetStatus::Disponivel);

        return app(RentalService::class)->reserve($asset, $customer, now()->addDays(3));
    }
}
