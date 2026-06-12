<?php

namespace App\Services;

use App\Enums\MaintenanceOrderType;
use App\Enums\RentalBillingQueueType;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\User;
use InvalidArgumentException;

class MaintenanceIndemnityService
{
    public function __construct(
        private readonly ReceivableTitleService $receivableTitleService,
    ) {}

    public function ensureReceivableTitle(MaintenanceOrder $order, ?User $user = null): ?ReceivableTitle
    {
        if (! $order->tipoEnum()->isIndenizacao()) {
            return null;
        }

        $order->loadMissing(['receivableTitle', 'rental.customer', 'customer']);

        if ($order->receivable_title_id && $order->receivableTitle) {
            return $order->receivableTitle;
        }

        $linked = $this->findExistingTitleForOrder($order);

        if ($linked) {
            $order->update(['receivable_title_id' => $linked->id]);

            return $linked;
        }

        return $this->receivableTitleService->createForIndemnityOrder($order, user: $user);
    }

    public function linkTitleFromBillingEntry(
        MaintenanceOrder $order,
        RentalBillingQueueEntry $entry,
    ): void {
        $entry->loadMissing('receivableTitle');

        if ($entry->receivableTitle) {
            $order->update([
                'receivable_title_id' => $entry->receivable_title_id,
                'valor_indenizacao' => $order->valor_indenizacao ?? $entry->valor_car,
            ]);

            $entry->receivableTitle->update([
                'maintenance_order_id' => $order->id,
            ]);
        }
    }

    private function findExistingTitleForOrder(MaintenanceOrder $order): ?ReceivableTitle
    {
        if ($order->rental_id) {
            $entry = RentalBillingQueueEntry::query()
                ->where('rental_id', $order->rental_id)
                ->where('tipo', RentalBillingQueueType::Indenizacao->value)
                ->whereNotNull('receivable_title_id')
                ->latest('id')
                ->first();

            if ($entry?->receivableTitle) {
                $entry->receivableTitle->update(['maintenance_order_id' => $order->id]);

                return $entry->receivableTitle;
            }

            $title = ReceivableTitle::query()
                ->where('rental_id', $order->rental_id)
                ->where('observacoes', 'like', '%Indenização%')
                ->latest('id')
                ->first();

            if ($title) {
                $title->update(['maintenance_order_id' => $order->id]);

                return $title;
            }
        }

        return null;
    }

    public function assertCanComplete(MaintenanceOrder $order): void
    {
        if (! $order->tipoEnum()->isIndenizacao()) {
            return;
        }

        if ($order->receivable_title_id) {
            return;
        }

        $valor = $order->valor_indenizacao !== null ? (float) $order->valor_indenizacao : 0;

        if ($valor <= 0) {
            throw new InvalidArgumentException(
                'Informe o valor de indenização antes de concluir a OS.'
            );
        }

        if ($order->resolvedCustomer() === null) {
            throw new InvalidArgumentException(
                'Informe o cliente cobrado na OS de indenização antes de concluir.'
            );
        }
    }
}
