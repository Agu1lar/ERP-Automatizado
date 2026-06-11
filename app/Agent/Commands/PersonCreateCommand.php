<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\Domain\Person\Person;
use App\Models\User;
use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;

class PersonCreateCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public static function name(): string
    {
        return 'person.create';
    }

    public static function description(): string
    {
        return 'Cadastra uma pessoa física no CRM.';
    }

    public function permission(): string
    {
        return 'people.manage';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['nome', 'cpf'],
            'properties' => [
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
        $data = $this->validatedPayload($input);
        $person = Person::create([
            ...$data,
            'created_by' => $user->id,
        ]);

        return $this->success(
            "Pessoa **{$person->nome}** cadastrada.",
            ['entity' => 'person', 'person' => ['id' => $person->id, 'nome' => $person->nome, 'cpf' => $person->cpf]],
            [['label' => 'Abrir pessoa', 'url' => route('people.show', $person), 'primary' => true]],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $data = $this->validatedPayload($input);

        return AgentCommandResult::preview(
            "Simulação: cadastrar pessoa **{$data['nome']}**.",
            ['nome' => $data['nome'], 'cpf' => $data['cpf']],
        );
    }

    /** @return array<string, mixed> */
    private function validatedPayload(array $input): array
    {
        $data = Validator::make($input, [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'max:14', 'unique:people,cpf', new ValidCpf],
            'data_nascimento' => ['nullable', 'date'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'telefone_secundario' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'endereco_residencial' => ['nullable', 'string'],
            'endereco_comercial' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ])->validate();

        return [
            'nome' => $data['nome'],
            'cpf' => preg_replace('/\D/', '', $data['cpf']),
            'data_nascimento' => $data['data_nascimento'] ?? null,
            'telefone' => filled($data['telefone'] ?? null) ? $data['telefone'] : null,
            'telefone_secundario' => filled($data['telefone_secundario'] ?? null) ? $data['telefone_secundario'] : null,
            'email' => filled($data['email'] ?? null) ? $data['email'] : null,
            'cargo' => filled($data['cargo'] ?? null) ? $data['cargo'] : null,
            'company_id' => $data['company_id'] ?? null,
            'endereco_residencial' => filled($data['endereco_residencial'] ?? null) ? $data['endereco_residencial'] : null,
            'endereco_comercial' => filled($data['endereco_comercial'] ?? null) ? $data['endereco_comercial'] : null,
            'observacoes' => filled($data['observacoes'] ?? null) ? $data['observacoes'] : null,
            'ativo' => $data['ativo'] ?? true,
        ];
    }
}
