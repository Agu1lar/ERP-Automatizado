<?php

namespace App\Services;

use App\Enums\OpportunityStage;
use App\Enums\RentalQuoteStatus;
use App\Models\Domain\Crm\CommercialOpportunity;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalQuote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CommercialOpportunityService
{
    public function createLead(
        Customer $customer,
        string $titulo,
        ?string $descricao = null,
        ?float $valorEstimado = null,
        ?User $assignedTo = null,
        ?CarbonInterface $proximoFollowUp = null,
        ?User $user = null,
    ): CommercialOpportunity {
        $user ??= auth()->user();

        return CommercialOpportunity::create([
            'customer_id' => $customer->id,
            'titulo' => trim($titulo),
            'descricao' => filled($descricao) ? trim($descricao) : null,
            'stage' => OpportunityStage::Lead->value,
            'valor_estimado' => $valorEstimado,
            'assigned_to' => $assignedTo?->id ?? $user?->id,
            'proximo_follow_up_em' => $proximoFollowUp?->toDateString(),
            'created_by' => $user?->id,
        ]);
    }

    public function moveStage(CommercialOpportunity $opportunity, OpportunityStage $stage, ?string $lostReason = null): CommercialOpportunity
    {
        if (! $opportunity->stageEnum()->isOpen() && $stage->isOpen()) {
            throw new InvalidArgumentException('Oportunidade encerrada não pode voltar ao pipeline.');
        }

        $payload = ['stage' => $stage->value];

        if ($stage === OpportunityStage::Ganho) {
            $payload['won_at'] = now();
            $payload['lost_at'] = null;
            $payload['lost_reason'] = null;
        } elseif ($stage === OpportunityStage::Perdido) {
            $payload['lost_at'] = now();
            $payload['won_at'] = null;
            $payload['lost_reason'] = filled($lostReason) ? trim($lostReason) : 'Sem motivo informado';
        }

        $opportunity->update($payload);

        return $opportunity->fresh(['customer', 'rentalQuote', 'assignedTo']);
    }

    public function syncFromQuote(RentalQuote $quote, ?User $user = null): CommercialOpportunity
    {
        $user ??= auth()->user();
        $quote->loadMissing(['customer', 'asset.equipmentModel']);

        $stage = match ($quote->statusEnum()) {
            RentalQuoteStatus::Rascunho => OpportunityStage::Proposta,
            RentalQuoteStatus::Enviado => OpportunityStage::Negociacao,
            RentalQuoteStatus::Convertido => OpportunityStage::Ganho,
            RentalQuoteStatus::Cancelado, RentalQuoteStatus::Expirado => OpportunityStage::Perdido,
        };

        $titulo = 'Orçamento '.$quote->codigo;
        if ($quote->asset) {
            $titulo .= ' — '.$quote->asset->codigo_patrimonio;
        }

        $opportunity = CommercialOpportunity::query()
            ->where('rental_quote_id', $quote->id)
            ->first();

        if (! $opportunity) {
            $opportunity = CommercialOpportunity::query()
                ->where('customer_id', $quote->customer_id)
                ->whereIn('stage', array_map(fn (OpportunityStage $s) => $s->value, OpportunityStage::pipelineStages()))
                ->whereNull('rental_quote_id')
                ->orderByDesc('id')
                ->first();
        }

        $payload = [
            'customer_id' => $quote->customer_id,
            'rental_quote_id' => $quote->id,
            'rental_id' => $quote->rental_id,
            'titulo' => $titulo,
            'stage' => $stage->value,
            'valor_estimado' => $quote->valor_estimado,
            'assigned_to' => $opportunity?->assigned_to ?? $quote->created_by ?? $user?->id,
        ];

        if ($stage === OpportunityStage::Ganho) {
            $payload['won_at'] = $quote->converted_at ?? now();
            $payload['lost_at'] = null;
            $payload['lost_reason'] = null;
        }

        if (in_array($stage, [OpportunityStage::Perdido], true)) {
            $payload['lost_at'] = now();
            $payload['lost_reason'] = match ($quote->statusEnum()) {
                RentalQuoteStatus::Cancelado => 'Orçamento cancelado',
                RentalQuoteStatus::Expirado => 'Orçamento expirado',
                default => 'Orçamento encerrado',
            };
        }

        if ($opportunity) {
            $opportunity->update($payload);

            return $opportunity->fresh();
        }

        $payload['created_by'] = $quote->created_by ?? $user?->id;

        return CommercialOpportunity::create($payload);
    }

    /** @return Collection<string, Collection<int, CommercialOpportunity>> */
    public function pipelineGrouped(): Collection
    {
        $items = CommercialOpportunity::query()
            ->with(['customer', 'rentalQuote', 'assignedTo'])
            ->whereIn('stage', array_map(fn (OpportunityStage $s) => $s->value, OpportunityStage::pipelineStages()))
            ->orderByDesc('updated_at')
            ->get();

        return collect(OpportunityStage::pipelineStages())
            ->mapWithKeys(fn (OpportunityStage $stage) => [
                $stage->value => $items->where('stage', $stage->value)->values(),
            ]);
    }

    public function openPipelineQuery(): Builder
    {
        return CommercialOpportunity::query()
            ->with(['customer', 'assignedTo'])
            ->whereIn('stage', array_map(fn (OpportunityStage $s) => $s->value, OpportunityStage::pipelineStages()));
    }

    public function dueFollowUpsQuery(): Builder
    {
        return CommercialOpportunity::query()
            ->with(['customer', 'assignedTo'])
            ->whereIn('stage', array_map(fn (OpportunityStage $s) => $s->value, OpportunityStage::pipelineStages()))
            ->whereNotNull('proximo_follow_up_em')
            ->whereDate('proximo_follow_up_em', '<=', now()->toDateString());
    }
}
