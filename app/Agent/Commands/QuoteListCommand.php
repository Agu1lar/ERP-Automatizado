<?php

namespace App\Agent\Commands;

use App\Enums\RentalQuoteStatus;
use App\Models\Domain\Rental\RentalQuote;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class QuoteListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'quote.list';
    }

    public static function description(): string
    {
        return 'Lista orçamentos/pré-reservas com filtros por status e busca textual.';
    }

    public function permission(): string
    {
        return 'rentals.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => array_column(RentalQuoteStatus::cases(), 'value')],
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 20), 1), 50);
        $status = ! empty($input['status']) ? (string) $input['status'] : null;

        $quotes = RentalQuote::query()
            ->with(['asset.equipmentModel.category', 'customer', 'rental'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when(! empty($input['q']), function ($q) use ($input) {
                $term = trim((string) $input['q']);
                $q->where(function ($q) use ($term) {
                    $q->where('codigo', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', fn ($c) => $c->where('nome', 'like', '%'.$term.'%'))
                        ->orWhereHas('asset', fn ($a) => $a->where('codigo_patrimonio', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $count = $quotes->count();
        $statusLabel = $status ? RentalQuoteStatus::from($status)->label() : null;

        $message = $count === 0
            ? 'Não encontrei orçamentos com esse filtro.'
            : "Encontrei **{$count}** orçamento(s)".($statusLabel ? " com status **{$statusLabel}**" : '').'.';

        $message .= "\n\nUse o atalho para abrir a listagem ou peça detalhes de um código ORC-….";

        return $this->success(
            $message,
            [
                'entity' => 'quote_list',
                'count' => $count,
                'quotes' => $quotes->map(fn (RentalQuote $quote) => [
                    'id' => $quote->id,
                    'codigo' => $quote->codigo,
                    'status' => $quote->status,
                    'status_label' => $quote->statusEnum()->label(),
                    'customer_nome' => $quote->customer?->nome,
                    'asset_codigo' => $quote->asset?->codigo_patrimonio,
                    'valid_until' => $quote->valid_until?->toDateString(),
                    'expired' => $quote->isExpired(),
                    'rental_codigo' => $quote->rental?->codigo,
                ])->all(),
            ],
            [
                ['label' => 'Abrir orçamentos', 'url' => CopilotNavigationLinks::quotes(['q' => $input['q'] ?? null, 'status' => $status]), 'primary' => true],
            ],
        );
    }
}
