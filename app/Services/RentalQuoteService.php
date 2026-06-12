<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalQuoteStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalQuote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RentalQuoteService
{
    public function __construct(
        private readonly RentalPricingService $pricingService,
        private readonly RentalService $rentalService,
        private readonly AuditService $auditService,
        private readonly CommercialOpportunityService $opportunityService,
    ) {}

    public function create(
        Asset $asset,
        Customer $customer,
        ?CarbonInterface $expectedReturn = null,
        ?string $localObra = null,
        ?string $observacoes = null,
        ?RentalPricingPeriod $pricingPeriod = null,
        ?User $user = null,
    ): RentalQuote {
        $user ??= auth()->user();

        if (! $customer->ativo) {
            throw new InvalidArgumentException('Cliente inativo.');
        }

        $quote = RentalQuote::create([
            'codigo' => $this->generateCodigo(),
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalQuoteStatus::Rascunho->value,
            'expected_return_at' => $expectedReturn,
            'local_obra' => filled($localObra) ? trim($localObra) : null,
            'observacoes' => $observacoes,
            'pricing_period' => $pricingPeriod?->value,
            'created_by' => $user?->id,
        ]);

        $quote = $this->refreshEstimate($quote);
        $this->opportunityService->syncFromQuote($quote, $user);

        return $quote;
    }

    public function send(RentalQuote $quote, int $validityDays = 7, ?User $user = null): RentalQuote
    {
        $user ??= auth()->user();

        if ($quote->statusEnum() !== RentalQuoteStatus::Rascunho) {
            throw new InvalidArgumentException('Somente orçamentos em rascunho podem ser enviados.');
        }

        $validityDays = max(1, min($validityDays, 90));
        $quote = $this->refreshEstimate($quote);

        $quote->update([
            'status' => RentalQuoteStatus::Enviado->value,
            'sent_at' => now(),
            'valid_until' => now()->addDays($validityDays),
        ]);

        $this->auditService->log(
            AuditAction::Updated,
            'RentalQuote',
            $quote->id,
            null,
            ['status' => RentalQuoteStatus::Enviado->value, 'valid_until' => $quote->valid_until],
            $user,
        );

        $quote = $quote->fresh(['asset', 'customer']);
        $this->opportunityService->syncFromQuote($quote, $user);

        return $quote;
    }

    public function convertToReservation(RentalQuote $quote, ?User $user = null): Rental
    {
        $user ??= auth()->user();

        if ($quote->statusEnum() !== RentalQuoteStatus::Enviado) {
            throw new InvalidArgumentException('Somente orçamentos enviados podem virar reserva.');
        }

        if ($quote->isExpired()) {
            throw new InvalidArgumentException('Orçamento expirado. Reenvie com nova validade.');
        }

        return DB::transaction(function () use ($quote, $user) {
            $quote = RentalQuote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if ($quote->statusEnum() !== RentalQuoteStatus::Enviado || $quote->isExpired()) {
                throw new InvalidArgumentException('Orçamento não está disponível para conversão.');
            }

            $pricingPeriod = $quote->pricing_period
                ? RentalPricingPeriod::from($quote->pricing_period)
                : null;

            $rental = $this->rentalService->reserve(
                $quote->asset,
                $quote->customer,
                $quote->expected_return_at,
                $quote->observacoes,
                $user,
                $quote->local_obra,
                $pricingPeriod,
            );

            $quote->update([
                'status' => RentalQuoteStatus::Convertido->value,
                'rental_id' => $rental->id,
                'converted_at' => now(),
                'converted_by' => $user?->id,
            ]);

            $this->auditService->log(
                AuditAction::Updated,
                'RentalQuote',
                $quote->id,
                null,
                ['rental_id' => $rental->id, 'status' => RentalQuoteStatus::Convertido->value],
                $user,
            );

            $this->opportunityService->syncFromQuote($quote->fresh(), $user);

            return $rental;
        });
    }

    public function cancel(RentalQuote $quote, ?User $user = null): RentalQuote
    {
        $user ??= auth()->user();

        if (in_array($quote->statusEnum(), [RentalQuoteStatus::Convertido, RentalQuoteStatus::Cancelado], true)) {
            throw new InvalidArgumentException('Orçamento não pode ser cancelado.');
        }

        $quote->update(['status' => RentalQuoteStatus::Cancelado->value]);

        $this->auditService->log(
            AuditAction::Updated,
            'RentalQuote',
            $quote->id,
            null,
            ['status' => RentalQuoteStatus::Cancelado->value],
            $user,
        );

        $quote = $quote->fresh();
        $this->opportunityService->syncFromQuote($quote, $user);

        return $quote;
    }

    public function expireDueQuotes(): int
    {
        return RentalQuote::query()
            ->where('status', RentalQuoteStatus::Enviado->value)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now())
            ->update(['status' => RentalQuoteStatus::Expirado->value]);
    }

    public function refreshEstimate(RentalQuote $quote): RentalQuote
    {
        $quote->loadMissing(['asset.equipmentModel']);

        if ($quote->expected_return_at === null) {
            return $quote;
        }

        $period = $quote->pricing_period
            ? RentalPricingPeriod::from($quote->pricing_period)
            : RentalPricingPeriod::Diaria;

        $start = now()->startOfDay();
        $end = $quote->expected_return_at->copy()->startOfDay();
        $pricing = $this->pricingService->calculate($quote->asset, $start, $end, $period);

        $quote->update([
            'valor_estimado' => round((float) ($pricing['valor_calculado'] ?? 0), 2),
        ]);

        return $quote->fresh();
    }

    private function generateCodigo(): string
    {
        $next = (RentalQuote::withoutGlobalScope('operating_company')->max('id') ?? 0) + 1;

        return 'ORC-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
