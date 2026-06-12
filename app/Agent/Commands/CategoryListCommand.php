<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class CategoryListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'category.list';
    }

    public static function description(): string
    {
        return 'Lista categorias de equipamento cadastradas (frota) com contagem de modelos.';
    }

    public function permission(): string
    {
        return 'fleet.categories.view';
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

    public function execute(array $input, User $user): AgentCommandResult
    {
        $term = trim((string) ($input['q'] ?? ''));
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);

        $categories = EquipmentCategory::query()
            ->withCount('models')
            ->when(! empty($input['active_only']), fn ($q) => $q->where('ativo', true))
            ->when($term !== '', fn ($q) => $q->where('nome', 'like', '%'.$term.'%'))
            ->orderBy('nome')
            ->limit($limit)
            ->get();

        $count = $categories->count();
        $message = $count === 0
            ? 'Nenhuma categoria encontrada.'
            : "**Categorias de equipamento** — {$count} registro(s).";

        return $this->success(
            $message,
            [
                'entity' => 'category_list',
                'count' => $count,
                'categories' => $categories->map(fn (EquipmentCategory $c) => [
                    'id' => $c->id,
                    'nome' => $c->nome,
                    'tipo_linha' => $c->tipo_linha,
                    'ativo' => $c->ativo,
                    'models_count' => $c->models_count,
                ])->all(),
            ],
            [
                ['label' => 'Abrir categorias', 'url' => CopilotNavigationLinks::categories(), 'primary' => true],
            ],
        );
    }
}
