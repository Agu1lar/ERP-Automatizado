<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class PartListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'part.list';
    }

    public static function description(): string
    {
        return 'Lista catálogo de peças de manutenção com estoque e alertas abaixo do mínimo.';
    }

    public function permission(): string
    {
        return 'maintenance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'q' => ['type' => 'string', 'description' => 'Código ou descrição da peça.'],
                'below_minimum_only' => ['type' => 'boolean'],
                'active_only' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $term = trim((string) ($input['q'] ?? ''));
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);

        $parts = PartCatalogItem::query()
            ->when(! empty($input['active_only']), fn ($q) => $q->where('ativo', true))
            ->when(! empty($input['below_minimum_only']), fn ($q) => $q->belowMinimum())
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like, $term) {
                    $inner->where('descricao', 'like', $like)
                        ->orWhere('codigo_peca', 'like', $like)
                        ->orWhere('codigo_alternativo', 'like', $like);
                });
            })
            ->orderBy('descricao')
            ->limit($limit)
            ->get();

        $belowCount = $parts->filter(fn (PartCatalogItem $p) => $p->isBelowMinimum())->count();
        $count = $parts->count();

        $message = $count === 0
            ? 'Nenhuma peça encontrada no catálogo.'
            : "**Catálogo de peças** — {$count} item(ns)".($belowCount > 0 ? ", **{$belowCount}** abaixo do estoque mínimo" : '').'.';

        return $this->success(
            $message,
            [
                'entity' => 'part_list',
                'count' => $count,
                'below_minimum_count' => $belowCount,
                'parts' => $parts->map(fn (PartCatalogItem $p) => [
                    'id' => $p->id,
                    'codigo_peca' => $p->codigo_peca,
                    'descricao' => $p->descricao,
                    'valor_unitario_padrao' => (float) $p->valor_unitario_padrao,
                    'estoque_atual' => (float) $p->estoque_atual,
                    'estoque_minimo' => $p->estoque_minimo !== null ? (float) $p->estoque_minimo : null,
                    'below_minimum' => $p->isBelowMinimum(),
                    'ativo' => $p->ativo,
                ])->all(),
            ],
            [
                ['label' => 'Abrir catálogo de peças', 'url' => CopilotNavigationLinks::partsCatalog(), 'primary' => true],
            ],
        );
    }
}
