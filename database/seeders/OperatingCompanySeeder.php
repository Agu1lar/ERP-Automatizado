<?php

namespace Database\Seeders;

use App\Models\Domain\Organization\OperatingCompany;
use Illuminate\Database\Seeder;

class OperatingCompanySeeder extends Seeder
{
    public function run(): void
    {
        OperatingCompany::updateOrCreate([
            'slug' => 'acesso',
        ], [
            'nome' => 'Acesso Equipamentos',
            'razao_social' => 'Acesso Equipamentos Ltda',
            'cnpj' => '12.345.678/0001-90',
            'endereco' => 'Rua Exemplo, 100',
            'telefone' => '(31) 3333-0000',
            'email' => 'contato@acesso.local',
            'ativo' => true,
        ]);

        OperatingCompany::updateOrCreate([
            'slug' => 'supermaquinas',
        ], [
            'nome' => 'Super Máquinas',
            'razao_social' => 'Super Máquinas S.A.',
            'cnpj' => '98.765.432/0001-10',
            'endereco' => 'Avenida Demo, 500',
            'telefone' => '(11) 4444-0000',
            'email' => 'contato@supermaquinas.local',
            'ativo' => true,
        ]);
    }
}
