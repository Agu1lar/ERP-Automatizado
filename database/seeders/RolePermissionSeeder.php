<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** @var list<string> */
    private array $permissions = [
        'dashboard.view',
        'dashboard.analytics',
        'fleet.categories.view',
        'fleet.categories.manage',
        'fleet.models.view',
        'fleet.models.manage',
        'fleet.assets.view',
        'fleet.assets.manage',
        'fleet.assets.change_status',
        'fleet.assets.attachments',
        'customers.view',
        'customers.manage',
        'people.view',
        'people.manage',
        'rentals.view',
        'rentals.reserve',
        'rentals.operate',
        'pricing.view',
        'pricing.manage',
        'finance.view',
        'finance.manage',
        'maintenance.view',
        'maintenance.manage',
        'maintenance.operate',
        'custom_fields.manage',
        'custom_fields.hide',
        'records.edit',
        'admin.users.view',
        'admin.users.manage',
        'admin.companies.manage',
        'audit.view',
        'agent.api',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $viewAll = [
            'dashboard.view',
            'fleet.categories.view',
            'fleet.models.view',
            'fleet.assets.view',
            'customers.view',
            'people.view',
            'rentals.view',
            'pricing.view',
            'finance.view',
            'maintenance.view',
        ];

        // Comercial, Operação e Manutenção — mesmo nível operacional
        $operationalBase = [
            ...$viewAll,
            'dashboard.analytics',
            'customers.manage',
            'people.manage',
            'rentals.reserve',
            'rentals.operate',
            'maintenance.operate',
            'records.edit',
            'fleet.assets.attachments',
            'fleet.assets.change_status',
        ];

        $rolePermissions = [
            UserRole::Admin->value => $this->permissions,

            UserRole::Gestor->value => [
                ...$operationalBase,
                'pricing.manage',
                'finance.manage',
                'custom_fields.manage',
                'custom_fields.hide',
                'maintenance.manage',
                'audit.view',
                'agent.api',
            ],

            UserRole::Comercial->value => $operationalBase,
            UserRole::Operacao->value => $operationalBase,
            UserRole::Manutencao->value => $operationalBase,
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        Role::query()->where('name', 'leitura')->delete();
    }
}
