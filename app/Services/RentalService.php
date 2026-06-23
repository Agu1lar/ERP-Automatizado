<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Enums\MaintenanceOrderType;
use App\Enums\RentalChecklistType;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalAssetSubstitution;
use App\Models\Domain\Rental\RentalChecklist;
use App\Models\User;
use App\Support\RentalFichaBuilder;
use App\Support\RentalScheduleConflictService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RentalService
{
    /** @var array<string, string> */
    public const CHECKLIST_SAIDA = [
        'visual_ok' => 'Equipamento em bom estado visual',
        'acessorios_ok' => 'Cabos e acessórios conferidos',
        'identificacao_ok' => 'Etiqueta/QR do patrimônio legível',
    ];

    /** @var array<string, string> */
    public const CHECKLIST_RETORNO = [
        'devolvido' => 'Equipamento devolvido pelo cliente',
        'visual_conferido' => 'Estado visual conferido',
        'acessorios_devolvidos' => 'Acessórios devolvidos',
        'anomalias_registradas' => 'Danos ou anomalias registrados (se houver)',
    ];

    public function __construct(
        private readonly AssetStatusService $assetStatusService,
        private readonly AssetMovementService $assetMovementService,
        private readonly AuditService $auditService,
        private readonly MaintenanceOrderService $maintenanceOrderService,
        private readonly RentalPricingService $rentalPricingService,
        private readonly ReceivableTitleService $receivableTitleService,
        private readonly RentalBillingService $rentalBillingService,
        private readonly RentalScheduleConflictService $scheduleConflict,
        private readonly RentalWorksiteGeocodingService $worksiteGeocoding,
    ) {}

    public function reserve(
        Asset $asset,
        Customer $customer,
        ?CarbonInterface $expectedReturn = null,
        ?string $observacoes = null,
        ?User $user = null,
        ?string $localObra = null,
        ?RentalPricingPeriod $pricingPeriod = null,
        ?CarbonInterface $scheduledStart = null,
        ?float $valorFaturamento = null,
    ): Rental {
        $user ??= auth()->user();

        $scheduledStartDate = $scheduledStart?->copy()->startOfDay();
        $isFutureReservation = $scheduledStartDate !== null
            && $scheduledStartDate->gt(now()->startOfDay());

        if ($isFutureReservation && $expectedReturn === null) {
            throw new InvalidArgumentException('Informe a previsão de retorno para reserva futura.');
        }

        if ($isFutureReservation && ! $this->assetAllowsFutureReservation($asset)) {
            throw new InvalidArgumentException('Patrimônio não está disponível para reserva futura neste momento.');
        }

        if (! $isFutureReservation) {
            if ($this->assetHasImmediateReservation($asset->id)) {
                throw new InvalidArgumentException('Patrimônio já possui locação ativa.');
            }

            if (! $asset->isAvailableForRental()) {
                throw new InvalidArgumentException('Patrimônio não está disponível para reserva.');
            }
        }

        if (! $customer->ativo) {
            throw new InvalidArgumentException('Cliente inativo não pode receber locação.');
        }

        $this->receivableTitleService->assertCustomerCanReceiveRental($customer);

        $rangeStart = $isFutureReservation ? $scheduledStartDate : now()->startOfDay();

        if ($expectedReturn !== null) {
            $conflict = $this->scheduleConflict->analyze(
                $asset->id,
                $rangeStart,
                $expectedReturn->copy()->startOfDay(),
            );

            if ($conflict->hasConflict) {
                throw new InvalidArgumentException($conflict->message);
            }
        }

        return DB::transaction(function () use ($asset, $customer, $expectedReturn, $observacoes, $user, $localObra, $pricingPeriod, $scheduledStartDate, $isFutureReservation, $rangeStart, $valorFaturamento) {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if (! $isFutureReservation) {
                if ($this->assetHasImmediateReservation($asset->id)) {
                    throw new InvalidArgumentException('Patrimônio já possui locação ativa.');
                }

                if (! $asset->isAvailableForRental()) {
                    throw new InvalidArgumentException('Patrimônio não está disponível para reserva.');
                }
            } elseif (! $this->assetAllowsFutureReservation($asset)) {
                throw new InvalidArgumentException('Patrimônio não está disponível para reserva futura neste momento.');
            }

            if ($expectedReturn !== null) {
                $recheck = $this->scheduleConflict->analyze(
                    $asset->id,
                    $rangeStart,
                    $expectedReturn->copy()->startOfDay(),
                );

                if ($recheck->hasConflict) {
                    throw new InvalidArgumentException($recheck->message);
                }
            }

            $ficha = RentalFichaBuilder::prefillForReservation($asset, $customer);
            $localObraValue = filled($localObra) ? trim($localObra) : $ficha['local_obra'];

            try {
                $rental = Rental::create([
                    'codigo' => $this->generateCodigo(),
                    'asset_id' => $asset->id,
                    'customer_id' => $customer->id,
                    'status' => RentalStatus::Reservado->value,
                    'reserved_at' => now(),
                    'scheduled_start_at' => $isFutureReservation ? $scheduledStartDate?->toDateString() : null,
                    'reserved_by' => $user?->id,
                    'commercial_user_id' => $user?->id,
                    'expected_return_at' => $expectedReturn,
                    'observacoes' => $observacoes,
                    'horimetro_saida' => $ficha['horimetro_saida'],
                    'ficha_descricao' => $ficha['ficha_descricao'],
                    'local_obra' => $localObraValue,
                    'regiao_geografica' => app(\App\Support\GeographicRegionClassifier::class)
                        ->classifyValue($localObraValue, $customer->endereco),
                ]);
            } catch (QueryException $exception) {
                if ($this->isActiveRentalUniqueViolation($exception)) {
                    throw new InvalidArgumentException('Patrimônio já possui locação ativa ou reserva imediata pendente.');
                }

                throw $exception;
            }

            if (! $isFutureReservation) {
                $this->assetStatusService->transition(
                    $asset,
                    AssetStatus::Reservado,
                    "Reserva {$rental->codigo}",
                    $user,
                );
            }

            $this->auditService->log(
                AuditAction::Created,
                'Rental',
                $rental->id,
                null,
                $rental->toArray(),
                $user,
            );

            if ($expectedReturn) {
                $this->rentalPricingService->applyToRental(
                    $rental->fresh(),
                    $pricingPeriod,
                    overwriteFaturamento: $valorFaturamento === null,
                );

                if ($valorFaturamento !== null) {
                    $rental->update(['valor_faturamento' => $valorFaturamento]);
                }
            } elseif ($valorFaturamento !== null) {
                $rental->update(['valor_faturamento' => $valorFaturamento]);
            }

            $rental = $rental->fresh(['asset', 'customer']);
            $estimatedAmount = $rental->valor_faturamento !== null ? (float) $rental->valor_faturamento : null;
            $this->receivableTitleService->assertCustomerCanReceiveRental($customer->fresh(), $estimatedAmount);

            return $rental;
        });
    }

    /** @param array<string, bool> $checkedItems */
    public function checkout(Rental $rental, array $checkedItems, ?string $observacoes = null, ?User $user = null): Rental
    {
        $user ??= auth()->user();
        $this->assertStatus($rental, RentalStatus::Reservado);

        if ($rental->isFutureReservation()) {
            throw new InvalidArgumentException('A data de início da reserva ainda não foi atingida.');
        }

        if ($this->assetHasOtherOccupancy($rental->asset_id, $rental->id)) {
            throw new InvalidArgumentException('Patrimônio ainda está em outra locação ativa.');
        }

        $this->validateChecklistItems(self::CHECKLIST_SAIDA, $checkedItems);

        return DB::transaction(function () use ($rental, $checkedItems, $observacoes, $user) {
            $this->createChecklist($rental, RentalChecklistType::Saida, $checkedItems, $observacoes, $user);

            $before = ['status' => $rental->status];

            $rental->update([
                'status' => RentalStatus::Locado->value,
                'checkout_at' => now(),
                'checkout_by' => $user?->id,
            ]);

            $asset = $rental->asset;
            $this->assetStatusService->transition(
                $asset,
                AssetStatus::Locado,
                "Saída locação {$rental->codigo}",
                $user,
            );

            $rental = $rental->fresh(['asset', 'customer']);
            $this->applyWorkSiteLocation($rental, $user);
            $this->syncWorksiteGeocode($rental);

            $this->auditService->log(
                AuditAction::StatusChanged,
                'Rental',
                $rental->id,
                $before,
                ['status' => RentalStatus::Locado->value, 'checkout_at' => $rental->checkout_at],
                $user,
            );

            $rental = $rental->fresh(['asset', 'customer', 'checklists.items']);
            $this->rentalPricingService->applyToRental(
                $rental,
                overwriteFaturamento: $rental->valor_faturamento === null,
            );
            $rental = $rental->fresh(['asset', 'customer', 'checklists.items']);
            $this->rentalBillingService->initializeOnCheckout($rental, $user);

            return $rental->fresh(['asset', 'customer', 'checklists.items', 'receivableTitles', 'items', 'billingQueueEntries']);
        });
    }

    public function extend(
        Rental $rental,
        CarbonInterface $newExpectedReturn,
        ?RentalPricingPeriod $pricingPeriod = null,
        ?User $user = null,
    ): Rental {
        $user ??= auth()->user();
        $this->assertStatus($rental, RentalStatus::Locado);

        if ($rental->expected_return_at === null) {
            throw new InvalidArgumentException('Locação sem previsão de retorno não pode ser prorrogada.');
        }

        if ($newExpectedReturn->copy()->startOfDay()->lte($rental->expected_return_at)) {
            throw new InvalidArgumentException('Nova data deve ser posterior ao vencimento atual.');
        }

        return DB::transaction(function () use ($rental, $newExpectedReturn, $pricingPeriod, $user) {
            $before = [
                'expected_return_at' => $rental->expected_return_at?->toDateString(),
                'valor_faturamento' => $rental->valor_faturamento,
            ];

            $rental->update(['expected_return_at' => $newExpectedReturn->toDateString()]);
            $pricing = $this->rentalPricingService->applyToRental($rental->fresh(), $pricingPeriod);

            $this->auditService->log(
                AuditAction::Updated,
                'Rental',
                $rental->id,
                $before,
                [
                    'expected_return_at' => $newExpectedReturn->toDateString(),
                    'valor_faturamento' => $rental->fresh()->valor_faturamento,
                    'pricing' => $pricing,
                ],
                $user,
            );

            return $rental->fresh(['asset', 'customer']);
        });
    }

    public function updateLocalObra(Rental $rental, ?string $localObra, ?User $user = null): Rental
    {
        $user ??= auth()->user();
        $value = filled($localObra) ? trim($localObra) : null;
        $rental->loadMissing('customer');
        $rental->update([
            'local_obra' => $value,
            'regiao_geografica' => app(\App\Support\GeographicRegionClassifier::class)
                ->classifyValue($value, $rental->customer?->endereco),
            'obra_latitude' => null,
            'obra_longitude' => null,
            'obra_geocode_precision' => null,
            'obra_geocoded_at' => null,
        ]);

        $rental = $rental->fresh(['asset', 'customer']);

        if ($rental->statusEnum() === RentalStatus::Locado) {
            $this->applyWorkSiteLocation($rental, $user);
            $this->syncWorksiteGeocode($rental);
        }

        return $rental->fresh(['asset', 'customer']);
    }

    public function updateScheduledStart(Rental $rental, ?CarbonInterface $scheduledStart, ?User $user = null): Rental
    {
        $user ??= auth()->user();
        $this->assertStatus($rental, RentalStatus::Reservado);

        $scheduledStartDate = $scheduledStart?->copy()->startOfDay();
        $wasFuture = $rental->isFutureReservation();

        if ($scheduledStartDate !== null
            && $rental->expected_return_at !== null
            && $scheduledStartDate->gt($rental->expected_return_at->copy()->startOfDay())) {
            throw new InvalidArgumentException('O início previsto não pode ser após a previsão de retorno.');
        }

        if ($rental->expected_return_at !== null) {
            $rangeStart = $scheduledStartDate ?? now()->startOfDay();
            $conflict = $this->scheduleConflict->analyze(
                $rental->asset_id,
                $rangeStart,
                $rental->expected_return_at->copy()->startOfDay(),
                $rental->id,
            );

            if ($conflict->hasConflict) {
                throw new InvalidArgumentException($conflict->message);
            }
        }

        $rental->update([
            'scheduled_start_at' => $scheduledStartDate?->toDateString(),
        ]);

        $rental = $rental->fresh(['asset', 'customer']);

        if ($wasFuture && ! $rental->isFutureReservation() && $rental->asset->isAvailableForRental()) {
            $this->assetStatusService->transition(
                $rental->asset,
                AssetStatus::Reservado,
                "Início antecipado — reserva {$rental->codigo}",
                $user,
            );
        }

        return $rental->fresh(['asset', 'customer']);
    }

    /** @param array<string, bool> $checkedItems */
    public function registerReturn(Rental $rental, array $checkedItems, ?string $observacoes = null, ?User $user = null): Rental
    {
        $user ??= auth()->user();
        $this->assertStatus($rental, RentalStatus::Locado);

        $this->validateChecklistItems(self::CHECKLIST_RETORNO, $checkedItems);

        return DB::transaction(function () use ($rental, $checkedItems, $observacoes, $user) {
            $this->createChecklist($rental, RentalChecklistType::Retorno, $checkedItems, $observacoes, $user);

            $before = ['status' => $rental->status];

            $rental->update([
                'status' => RentalStatus::EmInspecao->value,
                'returned_at' => now(),
                'returned_by' => $user?->id,
            ]);

            $this->assetStatusService->transition(
                $rental->asset,
                AssetStatus::EmInspecao,
                "Retorno locação {$rental->codigo}",
                $user,
            );

            $this->rentalBillingService->markItemsReturned($rental);
            $this->rentalBillingService->queueFreightRecolhida($rental->fresh(), $user);

            $this->auditService->log(
                AuditAction::StatusChanged,
                'Rental',
                $rental->id,
                $before,
                ['status' => RentalStatus::EmInspecao->value, 'returned_at' => $rental->returned_at],
                $user,
            );

            return $rental->fresh(['asset', 'customer', 'checklists.items', 'items']);
        });
    }

    public function completeInspection(
        Rental $rental,
        bool $sendToMaintenance = false,
        ?string $motivoManutencao = null,
        ?User $user = null,
    ): Rental {
        $user ??= auth()->user();
        $this->assertStatus($rental, RentalStatus::EmInspecao);

        if ($sendToMaintenance && blank($motivoManutencao)) {
            throw new InvalidArgumentException('Motivo obrigatório para enviar à manutenção.');
        }

        return DB::transaction(function () use ($rental, $sendToMaintenance, $motivoManutencao, $user) {
            $before = ['status' => $rental->status];

            $rental->update([
                'status' => RentalStatus::Concluido->value,
                'completed_at' => now(),
                'completed_by' => $user?->id,
            ]);

            $targetStatus = $sendToMaintenance ? AssetStatus::EmManutencao : AssetStatus::Disponivel;
            $motivo = $sendToMaintenance
                ? $motivoManutencao
                : "Inspeção concluída — locação {$rental->codigo}";

            $this->restoreOriginLocation($rental->fresh(), $user);

            $this->assetStatusService->transition($rental->asset->fresh(), $targetStatus, $motivo, $user);

            if ($sendToMaintenance) {
                $this->maintenanceOrderService->open(
                    $rental->asset->fresh(),
                    $motivoManutencao,
                    MaintenanceOrderType::RetornoLocacao,
                    rental: $rental,
                    user: $user,
                );
            }

            $this->auditService->log(
                AuditAction::StatusChanged,
                'Rental',
                $rental->id,
                $before,
                ['status' => RentalStatus::Concluido->value, 'completed_at' => $rental->completed_at],
                $user,
            );

            return $rental->fresh(['asset', 'customer', 'checklists.items']);
        });
    }

    public function completeInspectionWithIndemnity(
        Rental $rental,
        string $motivo,
        float $valorIndenizacao,
        ?User $user = null,
    ): Rental {
        $user ??= auth()->user();
        $this->assertStatus($rental, RentalStatus::EmInspecao);

        if (blank($motivo)) {
            throw new InvalidArgumentException('Motivo obrigatório para indenização.');
        }

        if ($valorIndenizacao <= 0) {
            throw new InvalidArgumentException('Valor de indenização deve ser maior que zero.');
        }

        return DB::transaction(function () use ($rental, $motivo, $valorIndenizacao, $user) {
            $before = ['status' => $rental->status];

            $rental->update([
                'status' => RentalStatus::Concluido->value,
                'completed_at' => now(),
                'completed_by' => $user?->id,
            ]);

            $this->restoreOriginLocation($rental->fresh(), $user);

            $this->assetStatusService->transition(
                $rental->asset->fresh(),
                AssetStatus::EmManutencao,
                "Indenização — locação {$rental->codigo}",
                $user,
            );

            $order = $this->maintenanceOrderService->open(
                $rental->asset->fresh(),
                $motivo,
                MaintenanceOrderType::Indenizacao,
                rental: $rental,
                user: $user,
            );

            $entry = $this->rentalBillingService->queueIndemnity(
                $rental->fresh(),
                $valorIndenizacao,
                "Indenização — OS {$order->codigo}: {$motivo}",
                invoiceImmediately: true,
                user: $user,
            );

            app(MaintenanceIndemnityService::class)->linkTitleFromBillingEntry(
                $order->fresh(),
                $entry->fresh(['receivableTitle']),
            );

            $order->update(['valor_indenizacao' => $valorIndenizacao]);

            $this->auditService->log(
                AuditAction::StatusChanged,
                'Rental',
                $rental->id,
                $before,
                ['status' => RentalStatus::Concluido->value, 'completed_at' => $rental->completed_at],
                $user,
            );

            return $rental->fresh(['asset', 'customer', 'checklists.items', 'maintenanceOrders', 'receivableTitles', 'billingQueueEntries']);
        });
    }

    public function cancel(Rental $rental, string $reason, ?User $user = null): Rental
    {
        $user ??= auth()->user();
        $this->assertStatus($rental, RentalStatus::Reservado);

        if (blank($reason)) {
            throw new InvalidArgumentException('Motivo obrigatório para cancelar reserva.');
        }

        return DB::transaction(function () use ($rental, $reason, $user) {
            $before = ['status' => $rental->status];

            $rental->update([
                'status' => RentalStatus::Cancelado->value,
                'cancelled_at' => now(),
                'cancelled_by' => $user?->id,
                'cancel_reason' => $reason,
            ]);

            $this->assetStatusService->transition(
                $rental->asset,
                AssetStatus::Disponivel,
                "Cancelamento reserva {$rental->codigo}: {$reason}",
                $user,
            );

            $this->auditService->log(
                AuditAction::StatusChanged,
                'Rental',
                $rental->id,
                $before,
                ['status' => RentalStatus::Cancelado->value, 'cancel_reason' => $reason],
                $user,
            );

            return $rental->fresh(['asset', 'customer']);
        });
    }

    /** @return array<string, string> */
    public function checklistTemplate(RentalChecklistType $tipo): array
    {
        return match ($tipo) {
            RentalChecklistType::Saida => self::CHECKLIST_SAIDA,
            RentalChecklistType::Retorno => self::CHECKLIST_RETORNO,
        };
    }

    private function generateCodigo(): string
    {
        $next = (Rental::withoutGlobalScope('operating_company')->max('id') ?? 0) + 1;

        return 'LOC-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /** @param array<string, string> $template @param array<string, bool> $checkedItems */
    private function validateChecklistItems(array $template, array $checkedItems): void
    {
        foreach (array_keys($template) as $key) {
            if (empty($checkedItems[$key])) {
                throw new InvalidArgumentException('Todos os itens do checklist devem ser marcados.');
            }
        }
    }

    /** @param array<string, bool> $checkedItems */
    private function createChecklist(
        Rental $rental,
        RentalChecklistType $tipo,
        array $checkedItems,
        ?string $observacoes,
        ?User $user,
    ): RentalChecklist {
        $template = $this->checklistTemplate($tipo);

        $checklist = RentalChecklist::create([
            'rental_id' => $rental->id,
            'tipo' => $tipo->value,
            'user_id' => $user?->id,
            'observacoes' => $observacoes,
            'completed_at' => now(),
        ]);

        foreach ($template as $key => $label) {
            $checklist->items()->create([
                'item_key' => $key,
                'item_label' => $label,
                'checked' => ! empty($checkedItems[$key]),
            ]);
        }

        return $checklist;
    }

    private function assertStatus(Rental $rental, RentalStatus $expected): void
    {
        if ($rental->statusEnum() !== $expected) {
            throw new InvalidArgumentException(
                "Locação deve estar com status {$expected->label()}."
            );
        }
    }

    private function syncWorksiteGeocode(Rental $rental): void
    {
        if (blank($rental->local_obra)) {
            $this->worksiteGeocoding->clearCoordinates($rental);

            return;
        }

        $this->worksiteGeocoding->geocodeAndStore($rental->fresh(['customer']));
    }

    private function applyWorkSiteLocation(Rental $rental, ?User $user): void
    {
        if (blank($rental->local_obra)) {
            return;
        }

        $asset = $rental->asset->fresh();
        $destino = trim($rental->local_obra);

        if ($asset->localizacao === $destino) {
            return;
        }

        if ($rental->localizacao_origem === null) {
            $rental->update(['localizacao_origem' => $asset->localizacao ?? '']);
        }

        $this->assetMovementService->moveLocation(
            $asset,
            $destino,
            "Local da obra — locação {$rental->codigo}",
            $user,
        );
    }

    private function restoreOriginLocation(Rental $rental, ?User $user): void
    {
        if ($rental->localizacao_origem === null) {
            return;
        }

        $asset = $rental->asset->fresh();
        $origem = $rental->localizacao_origem;

        if ($asset->localizacao === $origem) {
            return;
        }

        $this->assetMovementService->moveLocation(
            $asset,
            $origem,
            "Retorno ao pátio — locação {$rental->codigo}",
            $user,
        );
    }

    public function substituteAsset(
        Rental $rental,
        Asset $newAsset,
        ?string $motivo = null,
        ?User $user = null,
    ): Rental {
        $user ??= auth()->user();

        if (! in_array($rental->statusEnum(), [RentalStatus::Reservado, RentalStatus::Locado], true)) {
            throw new InvalidArgumentException('Substituição permitida apenas em locações reservadas ou locadas.');
        }

        if ($newAsset->id === $rental->asset_id) {
            throw new InvalidArgumentException('Selecione um patrimônio diferente do atual.');
        }

        if (! $newAsset->isAvailableForRental()) {
            throw new InvalidArgumentException('O patrimônio substituto não está disponível.');
        }

        if ($this->assetHasActiveRental($newAsset->id)) {
            throw new InvalidArgumentException('O patrimônio substituto já possui locação ativa.');
        }

        return DB::transaction(function () use ($rental, $newAsset, $motivo, $user) {
            $rental = Rental::query()->whereKey($rental->id)->lockForUpdate()->firstOrFail();
            $oldAsset = $rental->asset->fresh();
            $newAsset = Asset::query()->whereKey($newAsset->id)->lockForUpdate()->firstOrFail();

            if (! $newAsset->isAvailableForRental()) {
                throw new InvalidArgumentException('O patrimônio substituto não está disponível.');
            }

            $before = ['asset_id' => $rental->asset_id];

            RentalAssetSubstitution::create([
                'rental_id' => $rental->id,
                'from_asset_id' => $oldAsset->id,
                'to_asset_id' => $newAsset->id,
                'motivo' => $motivo,
                'horimetro_saida' => $oldAsset->horimetro,
                'horimetro_entrada' => $newAsset->horimetro,
                'substituted_by' => $user?->id,
                'substituted_at' => now(),
            ]);

            $rental->update(['asset_id' => $newAsset->id]);

            if ($rental->statusEnum() === RentalStatus::Reservado) {
                $this->assetStatusService->transition($oldAsset, AssetStatus::Disponivel, "Substituído na reserva {$rental->codigo}", $user);
                $this->assetStatusService->transition($newAsset, AssetStatus::Reservado, "Substituição na reserva {$rental->codigo}", $user);
            } else {
                $this->assetStatusService->transition(
                    $oldAsset,
                    AssetStatus::EmManutencaoCampo,
                    $motivo ?: "Substituído na locação {$rental->codigo}",
                    $user,
                );
                $this->assetStatusService->transition($newAsset, AssetStatus::Locado, "Substituição na locação {$rental->codigo}", $user);
                $this->applyWorkSiteLocation($rental->fresh(), $user);
            }

            $this->auditService->log(
                AuditAction::Updated,
                'Rental',
                $rental->id,
                $before,
                [
                    'asset_id' => $newAsset->id,
                    'from_asset_id' => $oldAsset->id,
                    'motivo' => $motivo,
                ],
                $user,
            );

            $rental = $rental->fresh(['asset.equipmentModel.category', 'customer', 'assetSubstitutions.fromAsset', 'assetSubstitutions.toAsset']);
            $this->rentalBillingService->syncActiveItem($rental, $user, $oldAsset);

            return $rental->fresh(['items']);
        });
    }

    public function transferCommercialUser(Rental $rental, User $newUser, ?User $actor = null): Rental
    {
        $actor ??= auth()->user();

        if ($rental->statusEnum() !== RentalStatus::Concluido) {
            throw new InvalidArgumentException('Só é possível transferir a responsabilidade comercial após a conclusão da locação.');
        }

        if (! $newUser->isActive()) {
            throw new InvalidArgumentException('O usuário selecionado está inativo.');
        }

        if ($rental->commercial_user_id === $newUser->id) {
            throw new InvalidArgumentException('Este usuário já é o responsável comercial da locação.');
        }

        return DB::transaction(function () use ($rental, $newUser, $actor) {
            $before = ['commercial_user_id' => $rental->commercial_user_id];

            $rental->update(['commercial_user_id' => $newUser->id]);

            $this->auditService->log(
                AuditAction::Updated,
                'Rental',
                $rental->id,
                $before,
                ['commercial_user_id' => $newUser->id],
                $actor,
            );

            return $rental->fresh(['commercialUser']);
        });
    }

    private function assetHasActiveRental(int $assetId): bool
    {
        return Rental::query()
            ->withoutGlobalScope('operating_company')
            ->where('asset_id', $assetId)
            ->active()
            ->exists();
    }

    private function assetHasImmediateReservation(int $assetId): bool
    {
        return Rental::query()
            ->withoutGlobalScope('operating_company')
            ->where('asset_id', $assetId)
            ->where('status', RentalStatus::Reservado->value)
            ->whereNull('scheduled_start_at')
            ->exists()
            || Rental::query()
                ->withoutGlobalScope('operating_company')
                ->where('asset_id', $assetId)
                ->whereIn('status', [RentalStatus::Locado->value, RentalStatus::EmInspecao->value])
                ->exists();
    }

    private function assetAllowsFutureReservation(Asset $asset): bool
    {
        return match ($asset->statusEnum()) {
            AssetStatus::Disponivel,
            AssetStatus::Locado,
            AssetStatus::Reservado => true,
            default => false,
        };
    }

    private function assetHasOtherOccupancy(int $assetId, int $excludeRentalId): bool
    {
        return Rental::query()
            ->withoutGlobalScope('operating_company')
            ->where('asset_id', $assetId)
            ->where('id', '!=', $excludeRentalId)
            ->whereIn('status', [RentalStatus::Locado->value, RentalStatus::EmInspecao->value])
            ->exists();
    }

    private function isActiveRentalUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rentals_one_active_per_asset')
            || str_contains($message, 'rentals_one_occupied_per_asset')
            || str_contains($message, 'rentals_one_immediate_reserve_per_asset');
    }
}
