<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@acesso.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Acesso@2026'),
                'ativo' => true,
            ],
        );

        $admin->assignRole(UserRole::Admin->value);
    }
}
