<?php

namespace App\Agent\Commands;

use App\Enums\MaintenanceOrderStatus;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class MaintenanceListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'maintenance.list';
    }

    public static function description(): string
    {
        return 'Lista ordens de serviço (OS). Para "N últimas OS abertas" use limit=N e open_only=true.';
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
                'status' => ['type' => 'string', 'enum' => array_column(MaintenanceOrderStatus::cases(), 'value')],
                'open_only' => ['type' => 'boolean'],
                'overdue_only' => ['type' => 'boolean'],
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);
        $openOnly = array_key_exists('open_only', $input) ? (bool) $input['open_only'] : true;
        $term = trim((string) ($input['q'] ?? ''));

        $orders = MaintenanceOrder::query()
            ->with(['asset.equipmentModel.category', 'assignedToUser'])
            ->when($openOnly, fn ($q) => $q->open())
            ->when(! empty($input['status']), fn ($q) => $q->where('status', $input['status']))
            ->when(! empty($input['overdue_only']), fn ($q) => $q->overdue())
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like, $term) {
                    $inner->where('codigo', 'like', $like)
                        ->orWhere('descricao_problema', 'like', $like)
                        ->orWhereHas('asset', fn ($aq) => $aq->where('codigo_patrimonio', 'like', $like));
                });
            })
            ->orderByDesc('opened_at')
            ->limit($limit)
            ->get();

        $count = $orders->count();
        $statusLabel = ! empty($input['status'])
            ? MaintenanceOrderStatus::from($input['status'])->label()
            : null;

        $message = $count === 0
            ? 'Não encontrei ordens de serviço com esse filtro.'
            : "Encontrei **{$count}** OS"
                .($statusLabel ? " com status **{$statusLabel}**" : '')
                .($openOnly ? ' (abertas)' : '')
                .'.';

        if ($count > 0 && $count <= 10) {
            $message .= "\n\n**Resumo:**";
            foreach ($orders as $order) {
                $asset = $order->asset?->codigo_patrimonio ?? '—';
                $message .= "\n• {$order->codigo} — {$order->statusEnum()->label()} — {$asset}"
                    .($order->descricao_problema ? ' — '.mb_substr($order->descricao_problema, 0, 60) : '');
            }
        }

        $nextSteps = [
            ['label' => 'Abrir manutenção', 'url' => CopilotNavigationLinks::maintenance(['q' => $term ?: null]), 'primary' => true],
        ];

        if ($count > 0 && $count <= 10) {
            foreach ($orders as $order) {
                $nextSteps[] = [
                    'label' => "Ver {$order->codigo}",
                    'url' => route('maintenance.show', $order),
                ];
            }
        }

        return $this->success(
            $message,
            [
                'entity' => 'maintenance_list',
                'count' => $count,
                'orders' => $orders->map(fn (MaintenanceOrder $order) => [
                    'id' => $order->id,
                    'codigo' => $order->codigo,
                    'status' => $order->status,
                    'status_label' => $order->statusEnum()->label(),
                    'tipo' => $order->tipo,
                    'prioridade' => $order->prioridade,
                    'asset_codigo' => $order->asset?->codigo_patrimonio,
                    'descricao' => $order->descricao_problema,
                    'assigned_to' => $order->assignedToUser?->name,
                ])->all(),
            ],
            $nextSteps,
        );
    }
}
