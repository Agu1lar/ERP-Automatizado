<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'Acesso@2026';

    public function run(): void
    {
        $users = [
            ['email' => 'admin@acesso.local', 'name' => 'Administrador', 'role' => UserRole::Admin],
            ['email' => 'gestor@acesso.local', 'name' => 'Gestor Demo', 'role' => UserRole::Gestor],
            ['email' => 'comercial@acesso.local', 'name' => 'Comercial Demo', 'role' => UserRole::Comercial],
            ['email' => 'operacao@acesso.local', 'name' => 'Operação Demo', 'role' => UserRole::Operacao],
            ['email' => 'manutencao@acesso.local', 'name' => 'Manutenção Demo', 'role' => UserRole::Manutencao],
        ];

        foreach ($users as $profile) {
            $user = User::updateOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['name'],
                    'password' => self::DEFAULT_PASSWORD,
                    'ativo' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$profile['role']->value]);
        }
    }
}
