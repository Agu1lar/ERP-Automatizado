<?php

namespace Database\Seeders;

use App\Models\Domain\Customer\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::firstOrCreate([
            'cpf_cnpj' => '12345678909',
        ], [
            'nome' => 'Construtora Horizonte Ltda',
            'contato' => 'João Silva',
            'telefone' => '(11) 98888-0000',
            'email' => 'joao@construtora.local',
            'endereco' => 'Rua das Obras, 123',
            'ativo' => true,
        ]);

        Customer::firstOrCreate([
            'cpf_cnpj' => '11222333000181',
        ], [
            'nome' => 'Obras Rápidas ME',
            'contato' => 'Maria Oliveira',
            'telefone' => '(11) 97777-0000',
            'email' => 'maria@obras.local',
            'endereco' => 'Av. Rápida, 456',
            'ativo' => true,
        ]);
    }
}
