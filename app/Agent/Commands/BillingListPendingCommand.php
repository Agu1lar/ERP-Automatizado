<?php

namespace App\Agent\Commands;

use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class BillingListPendingCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'billing.list_pending';
    }

    public static function description(): string
    {
        return 'Lista pendências na fila a faturar (pendente ou autorizado).';
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
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);

        $entries = RentalBillingQueueEntry::query()
            ->with(['customer', 'rental', 'receivableTitle'])
            ->pendingInvoice()
            ->orderBy('gerado_em')
            ->limit($limit)
            ->get();

        $totalCar = round((float) $entries->sum('valor_car'), 2);

        return $this->success(
            "**{$entries->count()}** pendência(s) a faturar — total **R$ ".number_format($totalCar, 2, ',', '.')."**.\n\n"
            .'Abra a fila para autorizar ou faturar em lote, ou diga "faturar FAT-…" para uma pendência específica.',
            [
                'entity' => 'billing_pending',
                'count' => $entries->count(),
                'total_car' => $totalCar,
                'entries' => $entries->map(fn ($e) => [
                    'id' => $e->id,
                    'codigo' => $e->codigo,
                    'tipo' => $e->tipo,
                    'status' => $e->status,
                    'valor_car' => (float) $e->valor_car,
                    'rental_codigo' => $e->rental?->codigo,
                    'customer_nome' => $e->customer?->nome,
                    'title_codigo' => $e->receivableTitle?->codigo,
                ])->all(),
            ],
            [
                ['label' => 'Abrir fila a faturar', 'url' => CopilotNavigationLinks::billingQueue(), 'primary' => true],
            ],
        );
    }
}
