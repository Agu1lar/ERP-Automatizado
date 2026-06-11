<?php

namespace Database\Seeders;

use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Organization\OperatingCompany;
use Illuminate\Database\Seeder;

class YardSeeder extends Seeder
{
    public function run(): void
    {
        $acesso = OperatingCompany::query()->where('slug', 'acesso')->first();

        if (! $acesso) {
            return;
        }

        $yards = [
            [
                'nome' => 'Pátio BH Principal',
                'cidade' => 'Belo Horizonte',
                'endereco' => 'Rua Exemplo, 100 — Pampulha',
                'telefone' => '(31) 3333-0001',
                'principal' => true,
            ],
            [
                'nome' => 'Filial Contagem',
                'cidade' => 'Contagem',
                'endereco' => 'Av. Industrial, 500',
                'telefone' => '(31) 3333-0002',
                'principal' => false,
            ],
            [
                'nome' => 'Filial Betim',
                'cidade' => 'Betim',
                'endereco' => 'Rod. BR-381, km 485',
                'telefone' => '(31) 3333-0003',
                'principal' => false,
            ],
        ];

        foreach ($yards as $data) {
            Yard::updateOrCreate([
                'operating_company_id' => $acesso->id,
                'nome' => $data['nome'],
            ], array_merge($data, ['ativo' => true]));
        }
    }
}
