<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\CompanyType;
use App\Models\User;
use App\Rules\ValidCpfCnpj;
use App\Services\CompanyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyUpdateCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    private const UPDATABLE = ['nome', 'cnpj', 'tipo', 'endereco', 'observacoes', 'ativo'];

    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    public static function name(): string
    {
        return 'company.update';
    }

    public static function description(): string
    {
        return 'Atualiza cadastro de empresa no CRM.';
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
                ['company_id', 'company_cnpj'],
            ],
            'properties' => [
                'company_id' => ['type' => 'integer'],
                'company_cnpj' => ['type' => 'string'],
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
        $company = $this->resolveCompany($input);
        $payload = $this->buildPayload($input, $company);

        if ($payload === [] && ! isset($input['contacts']) && ! isset($input['emails'])) {
            return $this->failure('Informe ao menos um campo para atualizar.', 'validation_failed');
        }

        DB::transaction(function () use ($company, $payload, $input) {
            if ($payload !== []) {
                $company->update($payload);
            }

            if (isset($input['contacts']) || isset($input['emails'])) {
                $this->companyService->syncContactsAndEmails(
                    $company,
                    $input['contacts'] ?? [],
                    $input['emails'] ?? [],
                );
            }
        });

        $company = $company->fresh();

        return $this->success(
            "Empresa **{$company->nome}** atualizada.",
            ['entity' => 'company', 'company' => ['id' => $company->id, 'nome' => $company->nome, 'ativo' => $company->ativo]],
            [['label' => 'Abrir empresas CRM', 'url' => route('companies.index'), 'primary' => true]],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $company = $this->resolveCompany($input);
        $payload = $this->buildPayload($input, $company);

        return AgentCommandResult::preview(
            'Simulação: atualizar empresa **'.$company->nome.'**.',
            ['company_id' => $company->id, 'changes' => array_keys($payload)],
        );
    }

    /** @return array<string, mixed> */
    private function buildPayload(array $input, \App\Models\Domain\Person\Company $company): array
    {
        $present = array_intersect_key($input, array_flip(self::UPDATABLE));

        if ($present === []) {
            return [];
        }

        $data = Validator::make($present, [
            'nome' => ['sometimes', 'string', 'max:255'],
            'cnpj' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('companies', 'cnpj')->ignore($company->id), new ValidCpfCnpj],
            'tipo' => ['sometimes', 'string', 'in:'.implode(',', array_column(CompanyType::cases(), 'value'))],
            'endereco' => ['sometimes', 'nullable', 'string'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ])->validate();

        if (array_key_exists('cnpj', $data)) {
            $data['cnpj'] = filled($data['cnpj']) ? preg_replace('/\D/', '', $data['cnpj']) : null;
        }

        return $data;
    }
}
