<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class ModelListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'model.list';
    }

    public static function description(): string
    {
        return 'Lista modelos de equipamento (marca/modelo) com categoria vinculada.';
    }

    public function permission(): string
    {
        return 'fleet.models.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category_id' => ['type' => 'integer'],
                'category_name' => ['type' => 'string'],
                'q' => ['type' => 'string', 'description' => 'Busca em marca ou modelo.'],
                'active_only' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $term = trim((string) ($input['q'] ?? ''));
        $categoryName = trim((string) ($input['category_name'] ?? ''));
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);

        $models = EquipmentModel::query()
            ->with('category:id,nome')
            ->withCount('assets')
            ->when(! empty($input['category_id']), fn ($q) => $q->where('equipment_category_id', (int) $input['category_id']))
            ->when($categoryName !== '', function ($q) use ($categoryName) {
                $q->whereHas('category', fn ($c) => $c->where('nome', 'like', '%'.$categoryName.'%'));
            })
            ->when(! empty($input['active_only']), fn ($q) => $q->where('ativo', true))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('marca', 'like', $like)->orWhere('modelo', 'like', $like);
                });
            })
            ->orderBy('marca')
            ->orderBy('modelo')
            ->limit($limit)
            ->get();

        $count = $models->count();
        $message = $count === 0
            ? 'Nenhum modelo encontrado.'
            : "**Modelos de equipamento** — {$count} registro(s).";

        return $this->success(
            $message,
            [
                'entity' => 'model_list',
                'count' => $count,
                'models' => $models->map(fn (EquipmentModel $m) => [
                    'id' => $m->id,
                    'nome' => $m->displayName(),
                    'marca' => $m->marca,
                    'modelo' => $m->modelo,
                    'category_id' => $m->equipment_category_id,
                    'category_nome' => $m->category?->nome,
                    'ativo' => $m->ativo,
                    'assets_count' => $m->assets_count,
                ])->all(),
            ],
            [
                ['label' => 'Abrir modelos', 'url' => CopilotNavigationLinks::models(), 'primary' => true],
            ],
        );
    }
}
