<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\CompanyType;
use App\Models\Domain\Person\Company;
use App\Models\User;
use App\Rules\ValidCpfCnpj;
use App\Services\CompanyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyCreateCommand extends AbstractAgentCommand implements SupportsDryRun
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    public static function name(): string
    {
        return 'company.create';
    }

    public static function description(): string
    {
        return 'Cadastra uma empresa no CRM (fornecedor, parceiro, etc.).';
    }

    public function permission(): string
    {
        return 'people.manage';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['nome'],
            'properties' => [
                'nome' => ['type' => 'string'],
                'cnpj' => ['type' => 'string'],
                'tipo' => ['type' => 'string', 'enum' => ['propria', 'externa', 'cliente']],
                'endereco' => ['type' => 'string'],
                'observacoes' => ['type' => 'string'],
                'ativo' => ['type' => 'boolean'],
                'contacts' => ['type' => 'array'],
                'emails' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $data = $this->validatedCore($input);

        $company = DB::transaction(function () use ($data, $input) {
            $company = Company::create($data);

            if (isset($input['contacts']) || isset($input['emails'])) {
                $this->companyService->syncContactsAndEmails(
                    $company,
                    $input['contacts'] ?? [],
                    $input['emails'] ?? [],
                );
            }

            return $company;
        });

        return $this->success(
            "Empresa **{$company->nome}** cadastrada.",
            ['entity' => 'company', 'company' => ['id' => $company->id, 'nome' => $company->nome, 'tipo' => $company->tipo]],
            [['label' => 'Abrir empresas CRM', 'url' => route('companies.index'), 'primary' => true]],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $data = $this->validatedCore($input);

        return AgentCommandResult::preview(
            "Simulação: cadastrar empresa **{$data['nome']}**.",
            ['nome' => $data['nome']],
        );
    }

    /** @return array<string, mixed> */
    private function validatedCore(array $input): array
    {
        $data = Validator::make($input, [
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20', 'unique:companies,cnpj', new ValidCpfCnpj],
            'tipo' => ['sometimes', 'string', 'in:'.implode(',', array_column(CompanyType::cases(), 'value'))],
            'endereco' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ])->validate();

        return [
            'nome' => $data['nome'],
            'cnpj' => filled($data['cnpj'] ?? null) ? preg_replace('/\D/', '', $data['cnpj']) : null,
            'tipo' => $data['tipo'] ?? CompanyType::Externa->value,
            'endereco' => filled($data['endereco'] ?? null) ? $data['endereco'] : null,
            'observacoes' => filled($data['observacoes'] ?? null) ? $data['observacoes'] : null,
            'ativo' => $data['ativo'] ?? true,
        ];
    }
}
