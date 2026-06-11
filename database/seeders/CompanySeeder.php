<?php

namespace Database\Seeders;

use App\Enums\CompanyType;
use App\Models\Domain\Person\Company;
use App\Services\CompanyService;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['nome' => 'ACESSO equipamentos'],
            [
                'tipo' => CompanyType::Propria->value,
                'endereco' => 'Belo Horizonte, MG',
                'ativo' => true,
            ],
        );

        app(CompanyService::class)->syncContactsAndEmails(
            $company,
            [
                [
                    'nome' => 'Recepção',
                    'cargo' => 'Atendimento',
                    'telefone' => '(31) 3333-0000',
                    'principal' => true,
                ],
            ],
            [
                [
                    'email' => 'contato@acesso.local',
                    'rotulo' => 'Geral',
                    'principal' => true,
                ],
                [
                    'email' => 'financeiro@acesso.local',
                    'rotulo' => 'Financeiro',
                    'principal' => false,
                ],
            ],
        );
    }
}
