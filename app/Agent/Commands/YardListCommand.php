<?php

namespace App\Agent\Commands;

use App\Models\Domain\Logistics\Yard;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class YardListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'yard.list';
    }

    public static function description(): string
    {
        return 'Lista pátios/base logística com quantidade de patrimônios vinculados.';
    }

    public function permission(): string
    {
        return 'fleet.assets.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'q' => ['type' => 'string'],
                'active_only' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);
        $term = trim((string) ($input['q'] ?? ''));

        $yards = Yard::query()
            ->withCount('assets')
            ->when(! empty($input['active_only']), fn ($q) => $q->where('ativo', true))
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('nome', 'like', $like)
                        ->orWhere('cidade', 'like', $like)
                        ->orWhere('endereco', 'like', $like);
                });
            })
            ->orderByDesc('principal')
            ->orderBy('nome')
            ->limit($limit)
            ->get();

        $count = $yards->count();
        $message = $count === 0
            ? 'Não encontrei pátios com esse filtro.'
            : "Encontrei **{$count}** pátio(s).";

        $message .= "\n\nAbra a tela de logística para editar ou vincular patrimônios.";

        return $this->success(
            $message,
            [
                'entity' => 'yard_list',
                'count' => $count,
                'yards' => $yards->map(fn (Yard $yard) => [
                    'id' => $yard->id,
                    'nome' => $yard->nome,
                    'cidade' => $yard->cidade,
                    'endereco' => $yard->endereco,
                    'ativo' => $yard->ativo,
                    'principal' => $yard->principal,
                    'assets_count' => $yard->assets_count,
                ])->all(),
            ],
            [
                ['label' => 'Abrir pátios', 'url' => CopilotNavigationLinks::yards($term ?: null), 'primary' => true],
            ],
        );
    }
}
