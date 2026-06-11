<?php

namespace App\Agent\Commands;

use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;

class PersonGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public static function name(): string
    {
        return 'person.get';
    }

    public static function description(): string
    {
        return 'Retorna detalhes de uma pessoa do cadastro CRM.';
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
                ['person_id', 'person_cpf'],
            ],
            'properties' => [
                'person_id' => ['type' => 'integer'],
                'person_cpf' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $person = $this->resolvePerson($input);
        $person->load(['company:id,nome,cnpj,tipo']);

        return $this->success(
            "Pessoa **{$person->nome}**.",
            [
                'entity' => 'person',
                'person' => [
                    'id' => $person->id,
                    'nome' => $person->nome,
                    'cpf' => $person->cpf,
                    'email' => $person->email,
                    'telefone' => $person->telefone,
                    'cargo' => $person->cargo,
                    'ativo' => $person->ativo,
                    'company' => $person->company ? [
                        'id' => $person->company->id,
                        'nome' => $person->company->nome,
                        'cnpj' => $person->company->cnpj,
                    ] : null,
                ],
            ],
            [['label' => 'Abrir pessoa', 'url' => route('people.show', $person), 'primary' => true]],
        );
    }
}
