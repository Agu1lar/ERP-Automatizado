<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\Domain\Customer\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CustomerUpdateCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    private const UPDATABLE = [
        'nome', 'email', 'telefone', 'endereco', 'contato', 'ativo', 'limite_credito', 'bloqueado', 'motivo_bloqueio',
    ];

    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'customer.update';
    }

    public static function description(): string
    {
        return 'Atualiza campos do cadastro de cliente, incluindo bloqueio manual.';
    }

    public function permission(): string
    {
        return 'customers.manage';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        try {
            $customer = $this->resolveCustomer($input);

            return [['type' => 'customer', 'id' => $customer->id]];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function declaredResourceTypes(): array
    {
        return ['customer'];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['customer_id', 'customer_cpf_cnpj'],
            ],
            'properties' => [
                'customer_id' => ['type' => 'integer'],
                'customer_cpf_cnpj' => ['type' => 'string'],
                'nome' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'telefone' => ['type' => 'string'],
                'endereco' => ['type' => 'string'],
                'contato' => ['type' => 'string'],
                'ativo' => ['type' => 'boolean'],
                'limite_credito' => ['type' => 'number'],
                'bloqueado' => ['type' => 'boolean'],
                'motivo_bloqueio' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $customer = $this->resolveCustomer($input);
        $payload = $this->buildPayload($input, $customer);

        if ($payload === []) {
            return $this->failure('Informe ao menos um campo para atualizar.', 'validation_failed');
        }

        if (array_key_exists('bloqueado', $payload)) {
            Customer::applyManualBlockPayload($payload, $user);
        }

        $customer->update($payload);
        $customer = $customer->fresh();

        $blockNote = $customer->bloqueado
            ? ' Cliente **bloqueado**.'
            : (array_key_exists('bloqueado', $payload) ? ' Bloqueio **removido**.' : '');

        return $this->success(
            "Cliente **{$customer->nome}** atualizado.{$blockNote}",
            $this->contextBuilder->customer($customer),
            [
                ['label' => 'Abrir ficha', 'url' => route('customers.show', $customer), 'primary' => true],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $customer = $this->resolveCustomer($input);
        $payload = $this->buildPayload($input, $customer);

        if ($payload === []) {
            return $this->failure('Informe ao menos um campo para atualizar.', 'validation_failed');
        }

        $fields = implode(', ', array_keys($payload));

        return AgentCommandResult::preview(
            "Simulação: atualizar cliente **{$customer->nome}** — campos: {$fields}.",
            [
                'customer_id' => $customer->id,
                'customer_nome' => $customer->nome,
                'changes' => $payload,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function buildPayload(array $input, Customer $customer): array
    {
        $present = array_intersect_key($input, array_flip(self::UPDATABLE));

        if ($present === []) {
            return [];
        }

        $rules = [
            'nome' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'endereco' => ['sometimes', 'nullable', 'string', 'max:500'],
            'contato' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
            'limite_credito' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'bloqueado' => ['sometimes', 'boolean'],
            'motivo_bloqueio' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];

        $data = Validator::make($present, $rules)->validate();

        if (array_key_exists('bloqueado', $data) && $data['bloqueado'] === true) {
            Validator::make($present, [
                'motivo_bloqueio' => ['required', 'string', 'max:2000'],
            ])->validate();
            $data['motivo_bloqueio'] = trim((string) ($present['motivo_bloqueio'] ?? ''));
        }

        if (array_key_exists('bloqueado', $data) && $data['bloqueado'] === false) {
            $data['motivo_bloqueio'] = null;
        }

        return $data;
    }
}
