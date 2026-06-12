<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PaymentMethod;
use App\Enums\ReceivableTitleStatus;
use App\Enums\RentalBillingQueueStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReceivableTitleService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /** @return Collection<int, ReceivableTitle> */
    public function generateForRental(
        Rental $rental,
        int $parcelas = 1,
        ?CarbonInterface $firstDueDate = null,
        ?User $user = null,
    ): Collection {
        $user ??= auth()->user();
        $rental->loadMissing('customer');

        if ($rental->valor_faturamento === null || (float) $rental->valor_faturamento <= 0) {
            throw new InvalidArgumentException('Locação sem valor de faturamento para gerar títulos.');
        }

        if ($parcelas < 1 || $parcelas > 24) {
            throw new InvalidArgumentException('Número de parcelas deve ser entre 1 e 24.');
        }

        if (ReceivableTitle::query()
            ->where('rental_id', $rental->id)
            ->where('status', '!=', ReceivableTitleStatus::Cancelado->value)
            ->exists()) {
            throw new InvalidArgumentException('Esta locação já possui títulos gerados.');
        }

        $firstDue = ($firstDueDate ?? $rental->expected_return_at ?? now()->addDays(7))->copy()->startOfDay();
        $total = round((float) $rental->valor_faturamento, 2);
        $baseParcel = floor(($total / $parcelas) * 100) / 100;
        $remainder = round($total - ($baseParcel * $parcelas), 2);

        return DB::transaction(function () use ($rental, $parcelas, $firstDue, $baseParcel, $remainder, $user) {
            $titles = collect();

            for ($i = 1; $i <= $parcelas; $i++) {
                $valor = $baseParcel + ($i === $parcelas ? $remainder : 0);
                $vencimento = $firstDue->copy()->addMonths($i - 1);

                $title = ReceivableTitle::create([
                    'codigo' => $this->generateCodigo(),
                    'customer_id' => $rental->customer_id,
                    'rental_id' => $rental->id,
                    'parcela' => $i,
                    'total_parcelas' => $parcelas,
                    'valor' => $valor,
                    'vencimento' => $vencimento,
                    'status' => ReceivableTitleStatus::Aberto->value,
                    'observacoes' => "Locação {$rental->codigo}",
                ]);

                $this->auditService->log(
                    AuditAction::Created,
                    'ReceivableTitle',
                    $title->id,
                    null,
                    $title->toArray(),
                    $user,
                );

                $titles->push($title);
            }

            return $titles;
        });
    }

    public function markAsPaid(
        ReceivableTitle $title,
        PaymentMethod $method,
        ?string $observacoes = null,
        ?CarbonInterface $paidAt = null,
        ?User $user = null,
    ): ReceivableTitle {
        $user ??= auth()->user();

        if ($title->statusEnum() !== ReceivableTitleStatus::Aberto) {
            throw new InvalidArgumentException('Somente títulos abertos podem receber baixa.');
        }

        $paidAt ??= now();
        $before = ['status' => $title->status, 'valor' => $title->valor];

        $title->update([
            'status' => ReceivableTitleStatus::Pago->value,
            'forma_pagamento' => $method->value,
            'pago_em' => $paidAt,
            'pago_por' => $user?->id,
            'observacoes_pagamento' => $observacoes,
        ]);

        $this->auditService->log(
            AuditAction::Updated,
            'ReceivableTitle',
            $title->id,
            $before,
            $title->fresh()->toArray(),
            $user,
        );

        return $title->fresh(['customer', 'rental']);
    }

    public function cancel(ReceivableTitle $title, ?string $reason = null, ?User $user = null): ReceivableTitle
    {
        $user ??= auth()->user();

        if ($title->statusEnum() === ReceivableTitleStatus::Pago) {
            throw new InvalidArgumentException('Título pago não pode ser cancelado.');
        }

        $before = ['status' => $title->status];

        $title->update([
            'status' => ReceivableTitleStatus::Cancelado->value,
            'observacoes' => trim(($title->observacoes ?? '').($reason ? "\nCancelado: {$reason}" : '')),
        ]);

        $this->auditService->log(
            AuditAction::Updated,
            'ReceivableTitle',
            $title->id,
            $before,
            ['status' => ReceivableTitleStatus::Cancelado->value],
            $user,
        );

        return $title->fresh();
    }

    public function customerHasOverdueTitles(Customer $customer): bool
    {
        return ReceivableTitle::query()
            ->where('customer_id', $customer->id)
            ->overdue()
            ->exists();
    }

    public function customerOpenBalance(Customer $customer): float
    {
        return (float) ReceivableTitle::query()
            ->where('customer_id', $customer->id)
            ->open()
            ->sum('valor');
    }

    public function customerOverdueBalance(Customer $customer): float
    {
        return (float) ReceivableTitle::query()
            ->where('customer_id', $customer->id)
            ->overdue()
            ->sum('valor');
    }

    public function assertCustomerCanReceiveRental(Customer $customer, ?float $newRentalAmount = null): void
    {
        if ($customer->isManuallyBlocked()) {
            $reason = $customer->motivo_bloqueio ?: 'Cliente bloqueado.';

            throw new InvalidArgumentException("Cliente bloqueado: {$reason}");
        }

        if ($customer->bloqueio_inadimplencia && $this->customerHasOverdueTitles($customer)) {
            throw new InvalidArgumentException('Cliente com títulos em atraso. Regularize o financeiro antes de nova locação.');
        }

        if ($newRentalAmount !== null && $customer->limite_credito !== null) {
            $exposure = $this->customerOpenBalance($customer) + $newRentalAmount;

            if ($exposure > (float) $customer->limite_credito) {
                throw new InvalidArgumentException(
                    'Limite de crédito excedido. Saldo em aberto + nova locação supera R$ '
                    .number_format((float) $customer->limite_credito, 2, ',', '.').'.'
                );
            }
        }
    }

    public function createForIndemnityOrder(
        MaintenanceOrder $order,
        ?CarbonInterface $dueDate = null,
        ?User $user = null,
    ): ReceivableTitle {
        $user ??= auth()->user();
        $order->loadMissing(['receivableTitle', 'rental', 'customer']);

        if ($order->receivable_title_id && $order->receivableTitle) {
            return $order->receivableTitle;
        }

        if (! $order->tipoEnum()->isIndenizacao()) {
            throw new InvalidArgumentException('Somente OS de indenização gera título por este fluxo.');
        }

        $customer = $order->resolvedCustomer();

        if ($customer === null) {
            throw new InvalidArgumentException('Cliente obrigatório para gerar título de indenização.');
        }

        $valor = round((float) ($order->valor_indenizacao ?? 0), 2);

        if ($valor <= 0) {
            throw new InvalidArgumentException('Valor de indenização deve ser maior que zero.');
        }

        $dueDate ??= now()->addDays(7);

        return DB::transaction(function () use ($order, $customer, $valor, $dueDate, $user) {
            $title = ReceivableTitle::create([
                'codigo' => $this->generateCodigo(),
                'customer_id' => $customer->id,
                'rental_id' => $order->rental_id,
                'maintenance_order_id' => $order->id,
                'parcela' => 1,
                'total_parcelas' => 1,
                'valor' => $valor,
                'vencimento' => $dueDate->copy()->startOfDay(),
                'status' => ReceivableTitleStatus::Aberto->value,
                'observacoes' => "Indenização — OS {$order->codigo}",
            ]);

            $order->update(['receivable_title_id' => $title->id]);

            $this->auditService->log(
                AuditAction::Created,
                'ReceivableTitle',
                $title->id,
                null,
                $title->toArray(),
                $user,
            );

            return $title->fresh();
        });
    }

    public function createForBillingQueueEntry(
        RentalBillingQueueEntry $entry,
        ?CarbonInterface $dueDate = null,
        ?User $user = null,
    ): ReceivableTitle {
        $user ??= auth()->user();
        $entry->loadMissing('rental.customer');

        if ($entry->receivable_title_id) {
            return $entry->receivableTitle()->firstOrFail();
        }

        $valor = round((float) $entry->valor_car, 2);

        if ($valor <= 0) {
            throw new InvalidArgumentException('Valor do título deve ser maior que zero.');
        }

        $dueDate ??= $entry->periodo_fim?->copy()->addDays(7) ?? now()->addDays(7);

        return DB::transaction(function () use ($entry, $valor, $dueDate, $user) {
            $rental = $entry->rental;
            $existingCount = ReceivableTitle::query()
                ->where('rental_id', $rental->id)
                ->where('status', '!=', ReceivableTitleStatus::Cancelado->value)
                ->count();

            $title = ReceivableTitle::create([
                'codigo' => $this->generateCodigo(),
                'customer_id' => $rental->customer_id,
                'rental_id' => $rental->id,
                'parcela' => max(1, $existingCount + 1),
                'total_parcelas' => max(1, $existingCount + 1),
                'valor' => $valor,
                'vencimento' => $dueDate->copy()->startOfDay(),
                'status' => ReceivableTitleStatus::Aberto->value,
                'observacoes' => trim($entry->observacoes ?? "Locação {$rental->codigo}"),
            ]);

            $entry->update(['receivable_title_id' => $title->id]);

            $this->auditService->log(
                AuditAction::Created,
                'ReceivableTitle',
                $title->id,
                null,
                $title->toArray(),
                $user,
            );

            return $title->fresh();
        });
    }

    public function updateOpenDueDate(
        ReceivableTitle $title,
        CarbonInterface $dueDate,
        ?User $user = null,
    ): ReceivableTitle {
        $user ??= auth()->user();

        if ($title->statusEnum() !== ReceivableTitleStatus::Aberto) {
            throw new InvalidArgumentException('Somente títulos em aberto podem ter o vencimento alterado.');
        }

        $entry = RentalBillingQueueEntry::query()
            ->where('receivable_title_id', $title->id)
            ->first();

        if ($entry && $entry->statusEnum() === RentalBillingQueueStatus::Faturado) {
            throw new InvalidArgumentException('Vencimento não pode ser alterado após a fatura ser gerada.');
        }

        $before = ['vencimento' => $title->vencimento?->toDateString()];

        $title->update(['vencimento' => $dueDate->copy()->startOfDay()]);

        $this->auditService->log(
            AuditAction::Updated,
            'ReceivableTitle',
            $title->id,
            $before,
            ['vencimento' => $title->fresh()->vencimento?->toDateString()],
            $user,
        );

        return $title->fresh();
    }

    public function syncTitleForInvoice(
        RentalBillingQueueEntry $entry,
        string $observacao,
        ?User $user = null,
    ): ReceivableTitle {
        $user ??= auth()->user();
        $entry->loadMissing(['rental', 'receivableTitle']);

        if ($entry->receivableTitle) {
            $title = $entry->receivableTitle;
            $before = ['valor' => $title->valor, 'observacoes' => $title->observacoes];

            $title->update([
                'valor' => round((float) $entry->valor_car, 2),
                'observacoes' => $observacao,
            ]);

            $this->auditService->log(
                AuditAction::Updated,
                'ReceivableTitle',
                $title->id,
                $before,
                ['valor' => $title->valor, 'observacoes' => $title->observacoes],
                $user,
            );

            return $title->fresh();
        }

        return $this->addSupplementaryTitle(
            $entry->rental,
            (float) $entry->valor_car,
            $observacao,
            $entry->periodo_fim?->copy()->addDays(7) ?? now()->addDays(7),
            $user,
        );
    }

    public function autoGenerateOnCheckout(Rental $rental, ?User $user = null): ?Collection
    {
        $rental->refresh();

        if ($rental->valor_faturamento === null || (float) $rental->valor_faturamento <= 0) {
            return null;
        }

        if (ReceivableTitle::query()
            ->where('rental_id', $rental->id)
            ->where('status', '!=', ReceivableTitleStatus::Cancelado->value)
            ->exists()) {
            return null;
        }

        return $this->generateForRental($rental, parcelas: 1, user: $user);
    }

    public function addSupplementaryTitle(
        Rental $rental,
        float $valor,
        string $descricao,
        ?CarbonInterface $dueDate = null,
        ?User $user = null,
    ): ReceivableTitle {
        $user ??= auth()->user();
        $rental->loadMissing('customer');

        if ($valor <= 0) {
            throw new InvalidArgumentException('Valor da cobrança complementar deve ser maior que zero.');
        }

        if (blank($descricao)) {
            throw new InvalidArgumentException('Descrição da cobrança complementar é obrigatória.');
        }

        return DB::transaction(function () use ($rental, $valor, $descricao, $dueDate, $user) {
            $rental->update([
                'valor_faturamento' => round((float) ($rental->valor_faturamento ?? 0) + $valor, 2),
            ]);

            $existingCount = ReceivableTitle::query()
                ->where('rental_id', $rental->id)
                ->where('status', '!=', ReceivableTitleStatus::Cancelado->value)
                ->count();

            $title = ReceivableTitle::create([
                'codigo' => $this->generateCodigo(),
                'customer_id' => $rental->customer_id,
                'rental_id' => $rental->id,
                'parcela' => max(1, $existingCount + 1),
                'total_parcelas' => max(1, $existingCount + 1),
                'valor' => round($valor, 2),
                'vencimento' => ($dueDate ?? now()->addDays(7))->copy()->startOfDay(),
                'status' => ReceivableTitleStatus::Aberto->value,
                'observacoes' => $descricao,
            ]);

            $this->auditService->log(
                AuditAction::Created,
                'ReceivableTitle',
                $title->id,
                null,
                $title->toArray(),
                $user,
            );

            return $title;
        });
    }

    private function generateCodigo(): string
    {
        $next = (ReceivableTitle::withoutGlobalScope('operating_company')->max('id') ?? 0) + 1;

        return 'TIT-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
