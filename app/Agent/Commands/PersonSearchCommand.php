<?php

namespace App\Agent\Commands;

use App\Models\Domain\Person\Person;
use App\Models\User;

class PersonSearchCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'person.search';
    }

    public static function description(): string
    {
        return 'Busca pessoas no cadastro CRM por nome, CPF ou e-mail.';
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

        $people = Person::query()
            ->with('company:id,nome')
            ->where(function ($q) use ($term, $digits) {
                $q->where('nome', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');

                if ($digits !== '') {
                    $q->orWhere('cpf', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome')
            ->limit($limit)
            ->get();

        return $this->success(
            "{$people->count()} pessoa(s) encontrada(s) para \"{$term}\".",
            [
                'entity' => 'person_search',
                'query' => $term,
                'count' => $people->count(),
                'people' => $people->map(fn (Person $p) => [
                    'id' => $p->id,
                    'nome' => $p->nome,
                    'cpf' => $p->cpf,
                    'email' => $p->email,
                    'cargo' => $p->cargo,
                    'company_nome' => $p->company?->nome,
                    'ativo' => $p->ativo,
                ])->all(),
            ],
        );
    }
}
