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
use App\Models\Domain\Rental\RentalChecklist;
use App\Models\User;
use App\Support\RentalFichaBuilder;
use Carbon\CarbonInterface;
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
    ) {}

    public function reserve(
        Asset $asset,
        Customer $customer,
        ?CarbonInterface $expectedReturn = null,
        ?string $observacoes = null,
        ?User $user = null,
        ?string $localObra = null,
        ?RentalPricingPeriod $pricingPeriod = null,
    ): Rental {
        $user ??= auth()->user();

        if (! $asset->isAvailableForRental()) {
            throw new InvalidArgumentException('Patrimônio não está disponível para reserva.');
        }

        if (! $customer->ativo) {
            throw new InvalidArgumentException('Cliente inativo não pode receber locação.');
        }

        if (Rental::query()->where('asset_id', $asset->id)->active()->exists()) {
            throw new InvalidArgumentException('Patrimônio já possui locação ativa.');
        }

        return DB::transaction(function () use ($asset, $customer, $expectedReturn, $observacoes, $user, $localObra, $pricingPeriod) {
            $ficha = RentalFichaBuilder::prefillForReservation($asset, $customer);

            $rental = Rental::create([
                'codigo' => $this->generateCodigo(),
                'asset_id' => $asset->id,
                'customer_id' => $customer->id,
                'status' => RentalStatus::Reservado->value,
                'reserved_at' => now(),
                'reserved_by' => $user?->id,
                'expected_return_at' => $expectedReturn,
                'observacoes' => $observacoes,
                'horimetro_saida' => $ficha['horimetro_saida'],
                'ficha_descricao' => $ficha['ficha_descricao'],
                'local_obra' => filled($localObra) ? trim($localObra) : $ficha['local_obra'],
            ]);

            $this->assetStatusService->transition(
                $asset,
                AssetStatus::Reservado,
                "Reserva {$rental->codigo}",
                $user,
            );

            $this->auditService->log(
                AuditAction::Created,
                'Rental',
                $rental->id,
                null,
                $rental->toArray(),
                $user,
            );

            if ($expectedReturn) {
                $this->rentalPricingService->applyToRental($rental->fresh(), $pricingPeriod);
            }

            return $rental->fresh(['asset', 'customer']);
        });
    }

    /** @param array<string, bool> $checkedItems */
    public function checkout(Rental $rental, array $checkedItems, ?string $observacoes = null, ?User $user = null): Rental
    {
        $user ??= auth()->user();
        $this->assertStatus($rental, RentalStatus::Reservado);

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

            $this->applyWorkSiteLocation($rental->fresh(), $user);

            $this->auditService->log(
                AuditAction::StatusChanged,
                'Rental',
                $rental->id,
                $before,
                ['status' => RentalStatus::Locado->value, 'checkout_at' => $rental->checkout_at],
                $user,
            );

            $rental = $rental->fresh(['asset', 'customer', 'checklists.items']);
            $this->rentalPricingService->applyToRental($rental);

            return $rental->fresh(['asset', 'customer', 'checklists.items']);
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
        $rental->update(['local_obra' => filled($localObra) ? trim($localObra) : null]);

        if ($rental->statusEnum() === RentalStatus::Locado) {
            $this->applyWorkSiteLocation($rental->fresh(), $user);
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

            $this->auditService->log(
                AuditAction::StatusChanged,
                'Rental',
                $rental->id,
                $before,
                ['status' => RentalStatus::EmInspecao->value, 'returned_at' => $rental->returned_at],
                $user,
            );

            return $rental->fresh(['asset', 'customer', 'checklists.items']);
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
        $next = (Rental::query()->max('id') ?? 0) + 1;

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
}
