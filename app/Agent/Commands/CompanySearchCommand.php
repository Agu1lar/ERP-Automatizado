<?php

namespace App\Agent\Commands;

use App\Models\Domain\Person\Company;
use App\Models\User;

class CompanySearchCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'company.search';
    }

    public static function description(): string
    {
        return 'Busca empresas no cadastro CRM por nome ou CNPJ.';
    }

    public function permission(): string
    {
        return 'people.view';
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

        $companies = Company::query()
            ->where(function ($q) use ($term, $digits) {
                $q->where('nome', 'like', '%'.$term.'%');

                if ($digits !== '') {
                    $q->orWhere('cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome')
            ->limit($limit)
            ->get();

        return $this->success(
            "{$companies->count()} empresa(s) encontrada(s) para \"{$term}\".",
            [
                'entity' => 'company_search',
                'query' => $term,
                'count' => $companies->count(),
                'companies' => $companies->map(fn (Company $c) => [
                    'id' => $c->id,
                    'nome' => $c->nome,
                    'cnpj' => $c->cnpj,
                    'tipo' => $c->tipo,
                    'ativo' => $c->ativo,
                ])->all(),
            ],
        );
    }
}
