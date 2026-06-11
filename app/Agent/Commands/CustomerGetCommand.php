<?php

namespace App\Agent\Commands;

use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;

class CustomerGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'customer.get';
    }

    public static function description(): string
    {
        return 'Retorna o contexto completo de um cliente (bloqueio, financeiro, inadimplência).';
    }

    public function permission(): string
    {
        return 'customers.view';
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
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $customer = $this->resolveCustomer($input);

        return $this->success(
            "Contexto do cliente {$customer->nome}.",
            $this->contextBuilder->customer($customer),
        );
    }
}
