<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\User;
use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PersonUpdateCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    private const UPDATABLE = [
        'nome', 'cpf', 'data_nascimento', 'telefone', 'telefone_secundario', 'email',
        'cargo', 'company_id', 'endereco_residencial', 'endereco_comercial', 'observacoes', 'ativo',
    ];

    public static function name(): string
    {
        return 'person.update';
    }

    public static function description(): string
    {
        return 'Atualiza cadastro de pessoa física no CRM.';
    }

    public function permission(): string
    {
        return 'people.manage';
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
                'nome' => ['type' => 'string'],
                'cpf' => ['type' => 'string'],
                'data_nascimento' => ['type' => 'string', 'format' => 'date'],
                'telefone' => ['type' => 'string'],
                'telefone_secundario' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'cargo' => ['type' => 'string'],
                'company_id' => ['type' => 'integer'],
                'endereco_residencial' => ['type' => 'string'],
                'endereco_comercial' => ['type' => 'string'],
                'observacoes' => ['type' => 'string'],
                'ativo' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $person = $this->resolvePerson($input);
        $payload = $this->buildPayload($input, $person);

        if ($payload === []) {
            return $this->failure('Informe ao menos um campo para atualizar.', 'validation_failed');
        }

        $person->update($payload);
        $person = $person->fresh();

        return $this->success(
            "Pessoa **{$person->nome}** atualizada.",
            ['entity' => 'person', 'person' => ['id' => $person->id, 'nome' => $person->nome, 'ativo' => $person->ativo]],
            [['label' => 'Abrir pessoa', 'url' => route('people.show', $person), 'primary' => true]],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $person = $this->resolvePerson($input);
        $payload = $this->buildPayload($input, $person);

        if ($payload === []) {
            return $this->failure('Informe ao menos um campo para atualizar.', 'validation_failed');
        }

        return AgentCommandResult::preview(
            'Simulação: atualizar **'.$person->nome.'** — '.implode(', ', array_keys($payload)).'.',
            ['person_id' => $person->id, 'changes' => array_keys($payload)],
        );
    }

    /** @return array<string, mixed> */
    private function buildPayload(array $input, \App\Models\Domain\Person\Person $person): array
    {
        $present = array_intersect_key($input, array_flip(self::UPDATABLE));

        if ($present === []) {
            return [];
        }

        $data = Validator::make($present, [
            'nome' => ['sometimes', 'string', 'max:255'],
            'cpf' => ['sometimes', 'string', 'max:14', Rule::unique('people', 'cpf')->ignore($person->id), new ValidCpf],
            'data_nascimento' => ['sometimes', 'nullable', 'date'],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'telefone_secundario' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'cargo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'endereco_residencial' => ['sometimes', 'nullable', 'string'],
            'endereco_comercial' => ['sometimes', 'nullable', 'string'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ])->validate();

        if (isset($data['cpf'])) {
            $data['cpf'] = preg_replace('/\D/', '', $data['cpf']);
        }

        return $data;
    }
}
