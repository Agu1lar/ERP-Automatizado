<?php

namespace App\Agent\Commands;

use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;
use App\Models\User;

class RentalListCommand extends AbstractAgentCommand
{
    public static function name(): string
    {
        return 'rental.list';
    }

    public static function description(): string
    {
        return 'Lista locações com filtros opcionais por status e busca textual.';
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
                'status' => ['type' => 'string', 'enum' => array_column(RentalStatus::cases(), 'value')],
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 20), 1), 50);

        $rentals = Rental::query()
            ->with(['customer', 'asset.equipmentModel'])
            ->when(! empty($input['status']), fn ($q) => $q->where('status', $input['status']))
            ->when(! empty($input['q']), function ($q) use ($input) {
                $term = trim((string) $input['q']);
                $q->where(function ($q) use ($term) {
                    $q->where('codigo', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', fn ($c) => $c->where('nome', 'like', '%'.$term.'%'))
                        ->orWhereHas('asset', fn ($a) => $a->where('codigo_patrimonio', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return $this->success(
            "{$rentals->count()} locação(ões) encontrada(s).",
            [
                'entity' => 'rental_list',
                'count' => $rentals->count(),
                'rentals' => $rentals->map(fn (Rental $r) => [
                    'id' => $r->id,
                    'codigo' => $r->codigo,
                    'status' => $r->status,
                    'status_label' => $r->statusEnum()->label(),
                    'customer_nome' => $r->customer?->nome,
                    'asset_codigo' => $r->asset?->codigo_patrimonio,
                    'expected_return_at' => $r->expected_return_at?->toDateString(),
                ])->all(),
            ],
        );
    }
}
