<?php

namespace App\Support;

use App\Enums\MaintenanceOrderStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;

class WorkflowNextStep
{
    public static function maintenanceShowUrl(MaintenanceOrder $order, ?string $acao = null): string
    {
        $url = route('maintenance.show', $order);

        if ($acao !== null) {
            $url .= '?acao='.urlencode($acao);
        }

        return $url;
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    public static function maintenanceAfterStart(MaintenanceOrder $order): array
    {
        $actions = [
            [
                'label' => 'Ver painel de manutenção',
                'url' => route('maintenance.index', ['aba' => 'painel']),
            ],
        ];

        if ($order->rental) {
            $actions[] = [
                'label' => 'Ficha '.$order->rental->codigo,
                'url' => route('rentals.show', $order->rental),
            ];
        }

        return $actions;
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    public static function maintenanceAfterWait(MaintenanceOrder $order): array
    {
        return [
            [
                'label' => 'Retomar nesta OS',
                'url' => self::maintenanceShowUrl($order, 'retomar'),
                'primary' => true,
            ],
            [
                'label' => 'Painel de manutenção',
                'url' => route('maintenance.index', ['aba' => 'painel']),
            ],
        ];
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    public static function maintenanceAfterResume(MaintenanceOrder $order): array
    {
        return [
            [
                'label' => 'Concluir OS',
                'url' => self::maintenanceShowUrl($order, 'concluir'),
                'primary' => true,
            ],
        ];
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    public static function maintenanceAfterComplete(MaintenanceOrder $order): array
    {
        $actions = [
            [
                'label' => 'Ver patrimônio',
                'url' => route('assets.show', $order->asset),
                'primary' => true,
            ],
        ];

        if ($order->rental) {
            $actions[] = [
                'label' => 'Ficha '.$order->rental->codigo,
                'url' => route('rentals.show', $order->rental),
            ];
        }

        return $actions;
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    public static function rentalAfterReserve(Rental $rental): array
    {
        return [
            [
                'label' => 'Registrar saída',
                'url' => route('rentals.show', $rental).'#workflow',
                'primary' => true,
            ],
            [
                'label' => 'Abrir ficha',
                'url' => route('rentals.show', $rental),
            ],
        ];
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    public static function rentalAfterReturn(Rental $rental): array
    {
        return [
            [
                'label' => 'Concluir inspeção',
                'url' => route('rentals.show', $rental).'?acao=inspecao',
                'primary' => true,
            ],
        ];
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    public static function rentalAfterCheckout(Rental $rental): array
    {
        return [
            [
                'label' => 'Ir para Faturamento',
                'url' => route('rentals.show', $rental).'#faturamento',
                'primary' => true,
            ],
            [
                'label' => 'Fila a faturar',
                'url' => route('finance.billing-queue'),
            ],
        ];
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    public static function customerBlocked(Customer $customer): array
    {
        $actions = [
            [
                'label' => 'Ver cliente',
                'url' => route('customers.show', $customer),
                'primary' => true,
            ],
        ];

        if ($customer->hasOverdueTitles()) {
            $actions[] = [
                'label' => 'Títulos em atraso',
                'url' => route('finance.delinquency'),
            ];
        }

        if (auth()->user()?->can('viewAny', \App\Models\Domain\Finance\ReceivableTitle::class)) {
            $actions[] = [
                'label' => 'Títulos a receber',
                'url' => route('finance.receivables', ['q' => $customer->nome]),
            ];
        }

        return $actions;
    }

    public static function maintenanceStatusHint(MaintenanceOrderStatus $status): ?string
    {
        return match ($status) {
            MaintenanceOrderStatus::Aberta => 'Próximo passo: iniciar execução e registrar peças/horas.',
            MaintenanceOrderStatus::EmExecucao => 'Próximo passo: registrar peças e horas ou concluir a OS.',
            MaintenanceOrderStatus::AguardandoPeca => 'Próximo passo: retomar execução quando a peça chegar.',
            default => null,
        };
    }
}

