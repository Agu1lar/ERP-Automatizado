<?php

namespace App\Agent\Commands;

use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;

class CompanyGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;
    public static function name(): string
    {
        return 'company.get';
    }

    public static function description(): string
    {
        return 'Retorna detalhes de uma empresa do cadastro CRM.';
    }

    public function permission(): string
    {
        return 'people.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['company_id', 'company_cnpj'],
            ],
            'properties' => [
                'company_id' => ['type' => 'integer'],
                'company_cnpj' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $company = $this->resolveCompany($input);
        $company->loadCount('people');

        return $this->success(
            "Empresa **{$company->nome}**.",
            [
                'entity' => 'company',
                'company' => [
                    'id' => $company->id,
                    'nome' => $company->nome,
                    'cnpj' => $company->cnpj,
                    'tipo' => $company->tipo,
                    'endereco' => $company->endereco,
                    'ativo' => $company->ativo,
                    'people_count' => $company->people_count,
                ],
            ],
            [['label' => 'Abrir empresas CRM', 'url' => route('companies.index'), 'primary' => true]],
        );
    }
}
