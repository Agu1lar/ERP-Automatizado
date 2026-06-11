<?php

namespace App\Agent\Commands;

use App\Enums\ReceivableTitleStatus;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class ReceivableListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'receivable.list';
    }

    public static function description(): string
    {
        return 'Lista títulos a receber com filtros por status, atraso e busca textual.';
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => array_column(ReceivableTitleStatus::cases(), 'value')],
                'overdue_only' => ['type' => 'boolean'],
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);

        $titles = ReceivableTitle::query()
            ->with(['customer', 'rental'])
            ->when(! empty($input['status']), fn ($q) => $q->where('status', $input['status']))
            ->when(! empty($input['overdue_only']), fn ($q) => $q->overdue())
            ->when(! empty($input['q']), function ($q) use ($input) {
                $term = trim((string) $input['q']);
                $q->where(function ($q) use ($term) {
                    $q->where('codigo', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', fn ($c) => $c->where('nome', 'like', '%'.$term.'%'))
                        ->orWhereHas('rental', fn ($r) => $r->where('codigo', 'like', '%'.$term.'%'));
                });
            })
            ->orderBy('vencimento')
            ->limit($limit)
            ->get();

        $count = $titles->count();
        $totalOpen = round((float) $titles->where('status', ReceivableTitleStatus::Aberto->value)->sum('valor'), 2);
        $overdueCount = $titles->filter(fn (ReceivableTitle $t) => $t->isOverdue())->count();

        $message = $count === 0
            ? 'Não encontrei títulos com esse filtro.'
            : "Encontrei **{$count}** título(s) — **R$ ".number_format($totalOpen, 2, ',', '.')."** em aberto nesta amostra.";

        if ($overdueCount > 0) {
            $message .= " **{$overdueCount}** em atraso.";
        }

        return $this->success(
            $message,
            [
                'entity' => 'receivable_list',
                'count' => $count,
                'total_open_sample' => $totalOpen,
                'overdue_count' => $overdueCount,
                'titles' => $titles->map(fn (ReceivableTitle $title) => [
                    'id' => $title->id,
                    'codigo' => $title->codigo,
                    'status' => $title->status,
                    'status_label' => $title->statusEnum()->label(),
                    'valor' => (float) $title->valor,
                    'vencimento' => $title->vencimento->toDateString(),
                    'overdue' => $title->isOverdue(),
                    'customer_nome' => $title->customer?->nome,
                    'rental_codigo' => $title->rental?->codigo,
                ])->all(),
            ],
            [
                ['label' => 'Abrir títulos', 'url' => CopilotNavigationLinks::financeReceivables($input['q'] ?? null), 'primary' => true],
            ],
        );
    }
}
