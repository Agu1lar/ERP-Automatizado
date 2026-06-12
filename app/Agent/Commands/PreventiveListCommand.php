<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class PreventiveListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'preventive.list';
    }

    public static function description(): string
    {
        return 'Lista regras de manutenção preventiva por modelo de equipamento.';
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
                'model_id' => ['type' => 'integer'],
                'category_name' => ['type' => 'string'],
                'active_only' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);
        $categoryName = trim((string) ($input['category_name'] ?? ''));

        $rules = PreventiveMaintenanceRule::query()
            ->with('equipmentModel.category')
            ->when(! empty($input['model_id']), fn ($q) => $q->where('equipment_model_id', (int) $input['model_id']))
            ->when($categoryName !== '', function ($q) use ($categoryName) {
                $q->whereHas('equipmentModel.category', fn ($c) => $c->where('nome', 'like', '%'.$categoryName.'%'));
            })
            ->when(! empty($input['active_only']), fn ($q) => $q->active())
            ->orderBy('equipment_model_id')
            ->orderBy('interval_horas')
            ->limit($limit)
            ->get();

        $count = $rules->count();
        $message = $count === 0
            ? 'Nenhuma regra preventiva encontrada.'
            : "**Regras preventivas** — {$count} regra(s) cadastrada(s).";

        return $this->success(
            $message,
            [
                'entity' => 'preventive_rule_list',
                'count' => $count,
                'rules' => $rules->map(fn (PreventiveMaintenanceRule $rule) => [
                    'id' => $rule->id,
                    'descricao' => $rule->descricao,
                    'interval_horas' => (float) $rule->interval_horas,
                    'ativo' => $rule->ativo,
                    'model_id' => $rule->equipment_model_id,
                    'model_nome' => $rule->equipmentModel?->displayName(),
                    'category_nome' => $rule->equipmentModel?->category?->nome,
                ])->all(),
            ],
            [
                ['label' => 'Abrir regras preventivas', 'url' => CopilotNavigationLinks::preventiveRules(), 'primary' => true],
            ],
        );
    }
}
