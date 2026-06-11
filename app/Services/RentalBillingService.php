<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\RentalBillingQueueStatus;
use App\Enums\RentalBillingQueueType;
use App\Enums\RentalStatus;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\Domain\Rental\RentalItem;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RentalBillingService
{
    public function __construct(
        private readonly RentalPricingService $pricingService,
        private readonly ReceivableTitleService $receivableTitleService,
        private readonly AuditService $auditService,
    ) {}

    public function initializeOnCheckout(
        Rental $rental,
        ?User $user = null,
        ?CarbonInterface $titleDueDate = null,
    ): void {
        $user ??= auth()->user();
        $rental->loadMissing('asset.equipmentModel');

        $start = $rental->checkout_at?->copy()->startOfDay() ?? now()->startOfDay();
        $cycleDays = max(1, (int) ($rental->billing_cycle_days ?: 28));
        $end = $start->copy()->addDays($cycleDays - 1);

        $rental->update([
            'billing_period_start' => $start,
            'billing_period_end' => $end,
            'next_billing_at' => $end->copy()->addDay(),
        ]);

        $this->syncActiveItem($rental, $user);
        $amount = $this->resolvePeriodAmount($rental);

        if ($amount > 0) {
            $dueDate = $titleDueDate
                ?? $rental->expected_return_at?->copy()
                ?? $end->copy()->addDays(7);

            $this->createQueueEntry(
                $rental->fresh(),
                RentalBillingQueueType::Locacao,
                $start,
                $end,
                $amount,
                $user,
                $dueDate,
            );
        }
    }

    public function syncActiveItem(Rental $rental, ?User $user = null): RentalItem
    {
        $rental->loadMissing('asset.equipmentModel.category');

        $asset = $rental->asset;
        $descricao = $asset->equipmentDisplayName()
            .($asset->equipmentModel?->category ? ' — '.$asset->equipmentModel->category->nome : '');

        $active = $rental->items()->where('ativo', true)->first();

        if ($active && $active->asset_id === $asset->id) {
            $active->update([
                'descricao' => $descricao,
                'local_entrega' => $rental->local_obra,
                'valor_locacao' => (float) ($rental->valor_calculado ?? $rental->valor_faturamento ?? 0),
                'valor_indenizacao' => $asset->valor_compra,
            ]);

            return $active->fresh();
        }

        if ($active) {
            $active->update(['ativo' => false]);
        }

        return RentalItem::create([
            'rental_id' => $rental->id,
            'asset_id' => $asset->id,
            'descricao' => $descricao,
            'quantidade' => 1,
            'valor_locacao' => (float) ($rental->valor_calculado ?? $rental->valor_faturamento ?? 0),
            'valor_indenizacao' => $asset->valor_compra,
            'local_entrega' => $rental->local_obra,
            'ativo' => true,
        ]);
    }

    public function markItemsReturned(Rental $rental): void
    {
        $rental->items()
            ->where('ativo', true)
            ->where('devolvido', false)
            ->update([
                'devolvido' => true,
                'devolvido_em' => now(),
            ]);
    }

    public function createRenewalIfDue(Rental $rental, ?User $user = null): ?RentalBillingQueueEntry
    {
        $user ??= auth()->user();

        if ($rental->statusEnum() !== RentalStatus::Locado) {
            return null;
        }

        if ($rental->next_billing_at === null || $rental->next_billing_at->gt(now()->startOfDay())) {
            return null;
        }

        $hasOpen = RentalBillingQueueEntry::query()
            ->where('rental_id', $rental->id)
            ->where('tipo', RentalBillingQueueType::Renovacao->value)
            ->pendingInvoice()
            ->exists();

        if ($hasOpen) {
            return null;
        }

        $start = $rental->next_billing_at->copy()->startOfDay();
        $cycleDays = max(1, (int) ($rental->billing_cycle_days ?: 28));
        $end = $start->copy()->addDays($cycleDays - 1);

        $this->pricingService->applyToRental($rental->fresh());
        $amount = $this->resolvePeriodAmount($rental->fresh());

        $rental->update([
            'billing_period_start' => $start,
            'billing_period_end' => $end,
            'next_billing_at' => $end->copy()->addDay(),
        ]);

        $rental->items()->where('ativo', true)->update([
            'valor_locacao' => $amount,
        ]);

        return $this->createQueueEntry(
            $rental->fresh(),
            RentalBillingQueueType::Renovacao,
            $start,
            $end,
            $amount,
            $user,
            $end->copy()->addDays(7),
        );
    }

    /** @return Collection<int, RentalBillingQueueEntry> */
    public function processDueRenewals(?User $user = null): Collection
    {
        $created = collect();

        Rental::query()
            ->where('status', RentalStatus::Locado->value)
            ->whereNotNull('next_billing_at')
            ->whereDate('next_billing_at', '<=', now()->toDateString())
            ->orderBy('next_billing_at')
            ->each(function (Rental $rental) use ($created, $user) {
                $entry = $this->createRenewalIfDue($rental, $user);
                if ($entry) {
                    $created->push($entry);
                }
            });

        return $created;
    }

    public function authorizeEntry(RentalBillingQueueEntry $entry, ?User $user = null): RentalBillingQueueEntry
    {
        $user ??= auth()->user();

        if ($entry->statusEnum() !== RentalBillingQueueStatus::Pendente) {
            throw new InvalidArgumentException('Somente pendências podem ser autorizadas.');
        }

        $entry->update([
            'status' => RentalBillingQueueStatus::Autorizado->value,
            'autorizado_em' => now(),
            'autorizado_por' => $user?->id,
        ]);

        return $entry->fresh();
    }

    public function invoiceEntry(RentalBillingQueueEntry $entry, ?User $user = null): RentalBillingQueueEntry
    {
        $user ??= auth()->user();

        if (! in_array($entry->statusEnum(), [RentalBillingQueueStatus::Pendente, RentalBillingQueueStatus::Autorizado], true)) {
            throw new InvalidArgumentException('Esta pendência não pode ser faturada.');
        }

        if ((float) $entry->valor_car <= 0) {
            throw new InvalidArgumentException('Valor a receber deve ser maior que zero.');
        }

        return DB::transaction(function () use ($entry, $user) {
            $rental = $entry->rental->fresh();
            $observacao = $this->titleObservation($entry);
            $title = $this->receivableTitleService->syncTitleForInvoice($entry, $observacao, $user);

            $entry->update([
                'status' => RentalBillingQueueStatus::Faturado->value,
                'faturado_em' => now(),
                'faturado_por' => $user?->id,
                'receivable_title_id' => $title->id,
                'autorizado_em' => $entry->autorizado_em ?? now(),
                'autorizado_por' => $entry->autorizado_por ?? $user?->id,
            ]);

            $rental->update(['last_billed_at' => $entry->periodo_fim ?? now()]);

            $this->auditService->log(
                AuditAction::Created,
                'RentalBillingQueue',
                $entry->id,
                null,
                ['receivable_title_id' => $title->id, 'valor' => $entry->valor_car],
                $user,
            );

            return $entry->fresh(['rental', 'customer', 'receivableTitle']);
        });
    }

    public function authorizeAndInvoice(RentalBillingQueueEntry $entry, ?User $user = null): RentalBillingQueueEntry
    {
        if ($entry->statusEnum() === RentalBillingQueueStatus::Pendente) {
            $entry = $this->authorizeEntry($entry, $user);
        }

        return $this->invoiceEntry($entry, $user);
    }

    public function updateBillingSettings(
        Rental $rental,
        int $cycleDays,
        ?float $minAmount,
    ): Rental {
        if ($cycleDays < 1 || $cycleDays > 365) {
            throw new InvalidArgumentException('Ciclo de faturamento deve ser entre 1 e 365 dias.');
        }

        $rental->update([
            'billing_cycle_days' => $cycleDays,
            'billing_min_amount' => $minAmount,
        ]);

        return $rental->fresh();
    }

    public function queueIndemnity(
        Rental $rental,
        float $amount,
        string $motivo,
        bool $invoiceImmediately = true,
        ?User $user = null,
    ): RentalBillingQueueEntry {
        $user ??= auth()->user();
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Valor de indenização deve ser maior que zero.');
        }

        $entry = RentalBillingQueueEntry::create([
            'codigo' => $this->generateCodigo(),
            'rental_id' => $rental->id,
            'customer_id' => $rental->customer_id,
            'tipo' => RentalBillingQueueType::Indenizacao->value,
            'periodo_inicio' => now()->toDateString(),
            'periodo_fim' => now()->toDateString(),
            'valor_nf' => $amount,
            'valor_car' => $amount,
            'status' => RentalBillingQueueStatus::Pendente->value,
            'gerado_em' => now(),
            'observacoes' => $motivo,
        ]);

        if ($invoiceImmediately) {
            return $this->authorizeAndInvoice($entry->fresh(), $user);
        }

        return $entry;
    }

    public function updateItemIndemnity(RentalItem $item, ?float $valorIndenizacao): RentalItem
    {
        $item->update(['valor_indenizacao' => $valorIndenizacao]);

        return $item->fresh();
    }

    private function createQueueEntry(
        Rental $rental,
        RentalBillingQueueType $tipo,
        CarbonInterface $start,
        CarbonInterface $end,
        float $amount,
        ?User $user,
        ?CarbonInterface $titleDueDate = null,
    ): RentalBillingQueueEntry {
        $amount = $this->applyMinimum($rental, $amount);

        $entry = RentalBillingQueueEntry::create([
            'codigo' => $this->generateCodigo(),
            'rental_id' => $rental->id,
            'customer_id' => $rental->customer_id,
            'tipo' => $tipo->value,
            'periodo_inicio' => $start,
            'periodo_fim' => $end,
            'valor_nf' => $amount,
            'valor_car' => $amount,
            'status' => RentalBillingQueueStatus::Pendente->value,
            'gerado_em' => now(),
            'observacoes' => $tipo->label().' — '.$rental->codigo,
        ]);

        if ($amount > 0) {
            $this->receivableTitleService->createForBillingQueueEntry(
                $entry->fresh(),
                $titleDueDate ?? $end->copy()->addDays(7),
                $user,
            );
        }

        return $entry->fresh(['receivableTitle']);
    }

    private function resolvePeriodAmount(Rental $rental): float
    {
        if ($rental->valor_calculado !== null && (float) $rental->valor_calculado > 0) {
            return round((float) $rental->valor_calculado, 2);
        }

        if ($rental->valor_faturamento !== null && (float) $rental->valor_faturamento > 0) {
            return round((float) $rental->valor_faturamento, 2);
        }

        $start = $rental->billing_period_start ?? $rental->checkout_at ?? $rental->reserved_at ?? now();
        $cycleDays = max(1, (int) ($rental->billing_cycle_days ?: 28));
        $end = $rental->billing_period_end ?? $start->copy()->addDays($cycleDays - 1);

        $pricing = $this->pricingService->calculate($rental->asset, $start, $end);

        return round((float) ($pricing['valor_calculado'] ?? 0), 2);
    }

    private function applyMinimum(Rental $rental, float $amount): float
    {
        $min = $rental->billing_min_amount;

        if ($min !== null && $amount > 0 && $amount < (float) $min) {
            return round((float) $min, 2);
        }

        return round($amount, 2);
    }

    private function titleObservation(RentalBillingQueueEntry $entry): string
    {
        $period = $entry->periodo_inicio && $entry->periodo_fim
            ? $entry->periodo_inicio->format('d/m/Y').' a '.$entry->periodo_fim->format('d/m/Y')
            : 'período não informado';

        return "{$entry->tipoEnum()->label()} {$entry->rental->codigo} ({$period})";
    }

    private function generateCodigo(): string
    {
        $next = (RentalBillingQueueEntry::withoutGlobalScope('operating_company')->max('id') ?? 0) + 1;

        return 'FAT-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
