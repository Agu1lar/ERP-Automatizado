<?php

namespace App\Agent\Commands;

use App\Models\Domain\Customer\Customer;
use App\Models\User;

class CustomerSearchCommand extends AbstractAgentCommand
{
    public static function name(): string
    {
        return 'customer.search';
    }

    public static function description(): string
    {
        return 'Busca clientes por nome ou documento (CPF/CNPJ).';
    }

    public function permission(): string
    {
        return 'customers.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['q'],
            'properties' => [
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $term = trim((string) $input['q']);
        $digits = preg_replace('/\D/', '', $term);
        $limit = min(max((int) ($input['limit'] ?? 15), 1), 30);

        $customers = Customer::query()
            ->where(function ($q) use ($term, $digits) {
                $q->where('nome', 'like', '%'.$term.'%');

                if ($digits !== '') {
                    $q->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome')
            ->limit($limit)
            ->get();

        return $this->success(
            "{$customers->count()} cliente(s) encontrado(s) para \"{$term}\".",
            [
                'entity' => 'customer_search',
                'query' => $term,
                'count' => $customers->count(),
                'customers' => $customers->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'nome' => $c->nome,
                    'cpf_cnpj' => $c->formattedDocument(),
                    'bloqueado' => $c->isManuallyBlocked(),
                    'ativo' => $c->ativo,
                ])->all(),
            ],
        );
    }
}
