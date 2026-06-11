<?php

namespace App\Support;

use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;

class RentalWorkflow
{
    /** @return list<array{key: string, label: string, state: string, hint: ?string}> */
    public static function steps(Rental $rental): array
    {
        $status = $rental->statusEnum();

        $stepStates = match ($status) {
            RentalStatus::Reservado => [
                'reserva' => 'current',
                'saida' => 'pending',
                'locacao' => 'pending',
                'retorno' => 'pending',
                'inspecao' => 'pending',
                'encerramento' => 'pending',
            ],
            RentalStatus::Locado => [
                'reserva' => 'completed',
                'saida' => 'completed',
                'locacao' => 'current',
                'retorno' => 'pending',
                'inspecao' => 'pending',
                'encerramento' => 'pending',
            ],
            RentalStatus::EmInspecao => [
                'reserva' => 'completed',
                'saida' => 'completed',
                'locacao' => 'completed',
                'retorno' => 'completed',
                'inspecao' => 'current',
                'encerramento' => 'pending',
            ],
            RentalStatus::Concluido => [
                'reserva' => 'completed',
                'saida' => 'completed',
                'locacao' => 'completed',
                'retorno' => 'completed',
                'inspecao' => 'completed',
                'encerramento' => 'completed',
            ],
            RentalStatus::Cancelado => [
                'reserva' => 'cancelled',
                'saida' => 'cancelled',
                'locacao' => 'cancelled',
                'retorno' => 'cancelled',
                'inspecao' => 'cancelled',
                'encerramento' => 'cancelled',
            ],
        };

        $definitions = [
            'reserva' => ['label' => 'Reserva', 'hint' => $rental->reserved_at?->format('d/m/Y H:i')],
            'saida' => ['label' => 'Saída', 'hint' => $rental->checkout_at?->format('d/m/Y H:i')],
            'locacao' => ['label' => 'Em locação', 'hint' => $rental->expected_return_at?->format('Retorno prev.: d/m/Y')],
            'retorno' => ['label' => 'Retorno', 'hint' => $rental->returned_at?->format('d/m/Y H:i')],
            'inspecao' => ['label' => 'Inspeção', 'hint' => $status === RentalStatus::EmInspecao ? 'Aguardando conclusão' : null],
            'encerramento' => ['label' => 'Encerrada', 'hint' => $rental->completed_at?->format('d/m/Y H:i') ?? $rental->cancelled_at?->format('d/m/Y H:i')],
        ];

        return collect($definitions)->map(fn (array $def, string $key) => [
            'key' => $key,
            'label' => $def['label'],
            'state' => $stepStates[$key],
            'hint' => $def['hint'],
        ])->values()->all();
    }

    public static function canOpenMaintenanceOrder(Rental $rental): bool
    {
        if ($rental->statusEnum() === RentalStatus::Cancelado) {
            return false;
        }

        return ! $rental->maintenanceOrders()->open()->exists();
    }

    public static function canGenerateReceivables(Rental $rental): bool
    {
        return $rental->pendingBillingEntries()->exists();
    }
}
