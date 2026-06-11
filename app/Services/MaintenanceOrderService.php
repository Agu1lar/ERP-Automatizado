<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\MaintenancePriority;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceLaborHour;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\MaintenancePart;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaintenanceOrderService
{
    public function __construct(
        private readonly AssetStatusService $assetStatusService,
        private readonly AuditService $auditService,
    ) {}

    public function open(
        Asset $asset,
        string $descricaoProblema,
        MaintenanceOrderType $tipo = MaintenanceOrderType::Corretiva,
        MaintenancePriority $prioridade = MaintenancePriority::Normal,
        bool $impeditiva = true,
        ?CarbonInterface $expectedCompletion = null,
        ?int $assignedTo = null,
        ?Rental $rental = null,
        ?string $observacoes = null,
        ?User $user = null,
    ): MaintenanceOrder {
        $user ??= auth()->user();

        if (blank($descricaoProblema)) {
            throw new InvalidArgumentException('Descrição do problema é obrigatória.');
        }

        if (MaintenanceOrder::query()->where('asset_id', $asset->id)->open()->exists()) {
            throw new InvalidArgumentException('Patrimônio já possui OS aberta.');
        }

        return DB::transaction(function () use (
            $asset,
            $descricaoProblema,
            $tipo,
            $prioridade,
            $impeditiva,
            $expectedCompletion,
            $assignedTo,
            $rental,
            $observacoes,
            $user,
        ) {
            $order = MaintenanceOrder::create([
                'codigo' => $this->generateCodigo(),
                'asset_id' => $asset->id,
                'rental_id' => $rental?->id,
                'customer_id' => $rental?->customer_id ?? $asset->activeRental()?->customer_id,
                'status' => MaintenanceOrderStatus::Aberta->value,
                'tipo' => $tipo->value,
                'prioridade' => $prioridade->value,
                'impeditiva' => $impeditiva,
                'descricao_problema' => $descricaoProblema,
                'observacoes' => $observacoes,
                'opened_at' => now(),
                'opened_by' => $user?->id,
                'assigned_to' => $assignedTo,
                'expected_completion_at' => $expectedCompletion,
            ]);

            $this->syncAssetToMaintenance($asset, "Abertura OS {$order->codigo}", $user);

            $this->auditService->log(
                AuditAction::Created,
                'MaintenanceOrder',
                $order->id,
                null,
                $order->toArray(),
                $user,
            );

            return $order->fresh(['asset', 'rental']);
        });
    }

    public function openPreventive(
        Asset $asset,
        PreventiveMaintenanceRule $rule,
        ?User $user = null,
    ): MaintenanceOrder {
        if ($rule->equipment_model_id !== $asset->equipment_model_id) {
            throw new InvalidArgumentException('Regra preventiva não se aplica a este patrimônio.');
        }

        $order = $this->open(
            $asset,
            "Manutenção preventiva: {$rule->descricao}",
            MaintenanceOrderType::Preventiva,
            MaintenancePriority::Normal,
            false,
            null,
            null,
            null,
            "Intervalo configurado: a cada {$rule->interval_horas} horas de uso.",
            $user,
        );

        $order->update(['preventive_rule_id' => $rule->id]);

        return $order->fresh(['asset', 'preventiveRule']);
    }

    public function start(MaintenanceOrder $order, ?User $user = null): MaintenanceOrder
    {
        $user ??= auth()->user();
        $this->assertStatus($order, MaintenanceOrderStatus::Aberta);

        return DB::transaction(function () use ($order, $user) {
            $before = ['status' => $order->status];

            $order->update([
                'status' => MaintenanceOrderStatus::EmExecucao->value,
                'started_at' => now(),
            ]);

            $this->transitionAssetIfNeeded(
                $order->asset,
                AssetStatus::EmManutencao,
                "Início OS {$order->codigo}",
                $user,
            );

            $this->auditStatusChange($order, $before, MaintenanceOrderStatus::EmExecucao, $user);

            return $order->fresh(['asset']);
        });
    }

    public function waitForPart(MaintenanceOrder $order, ?string $observacao = null, ?User $user = null): MaintenanceOrder
    {
        $user ??= auth()->user();
        $this->assertStatus($order, MaintenanceOrderStatus::EmExecucao);

        return DB::transaction(function () use ($order, $observacao, $user) {
            $before = ['status' => $order->status];

            $order->update([
                'status' => MaintenanceOrderStatus::AguardandoPeca->value,
                'observacoes' => trim(($order->observacoes ?? '').($observacao ? "\n{$observacao}" : '')) ?: null,
            ]);

            $this->transitionAssetIfNeeded(
                $order->asset,
                AssetStatus::AguardandoPeca,
                "Aguardando peça — OS {$order->codigo}",
                $user,
            );

            $this->auditStatusChange($order, $before, MaintenanceOrderStatus::AguardandoPeca, $user);

            return $order->fresh(['asset']);
        });
    }

    public function resume(MaintenanceOrder $order, ?User $user = null): MaintenanceOrder
    {
        $user ??= auth()->user();
        $this->assertStatus($order, MaintenanceOrderStatus::AguardandoPeca);

        return DB::transaction(function () use ($order, $user) {
            $before = ['status' => $order->status];

            $order->update([
                'status' => MaintenanceOrderStatus::EmExecucao->value,
            ]);

            $this->transitionAssetIfNeeded(
                $order->asset,
                AssetStatus::EmManutencao,
                "Retomada OS {$order->codigo}",
                $user,
            );

            $this->auditStatusChange($order, $before, MaintenanceOrderStatus::EmExecucao, $user);

            return $order->fresh(['asset']);
        });
    }

    public function updateTechnicalData(
        MaintenanceOrder $order,
        ?string $diagnostico = null,
        ?string $solucaoAplicada = null,
        ?int $assignedTo = null,
        ?CarbonInterface $expectedCompletion = null,
        ?string $parecerTecnico = null,
        ?int $customerId = null,
        ?string $assinaturaCaixa = null,
        ?string $assinaturaOrcadoPor = null,
        ?string $assinaturaMontadoPor = null,
        ?string $assetVoltagem = null,
    ): MaintenanceOrder {
        if (! $order->statusEnum()->isOpen()) {
            throw new InvalidArgumentException('OS encerrada não pode ser editada.');
        }

        $order->update(array_filter([
            'diagnostico' => $diagnostico,
            'solucao_aplicada' => $solucaoAplicada,
            'assigned_to' => $assignedTo,
            'expected_completion_at' => $expectedCompletion,
            'parecer_tecnico' => $parecerTecnico,
            'customer_id' => $customerId,
            'assinatura_caixa' => $assinaturaCaixa,
            'assinatura_orcado_por' => $assinaturaOrcadoPor,
            'assinatura_montado_por' => $assinaturaMontadoPor,
        ], fn ($value) => $value !== null));

        if ($assetVoltagem !== null) {
            $order->asset->update(['voltagem' => $assetVoltagem ?: null]);
        }

        return $order->fresh(['asset', 'customer', 'rental.customer']);
    }

    public function addPart(
        MaintenanceOrder $order,
        string $descricao,
        float $quantidade = 1,
        ?string $codigoPeca = null,
        ?float $valorUnitario = null,
        ?string $observacao = null,
        ?string $codigoAlternativo = null,
    ): MaintenancePart {
        if (! $order->statusEnum()->isOpen()) {
            throw new InvalidArgumentException('Não é possível adicionar peças em OS encerrada.');
        }

        if ($quantidade <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        return $order->parts()->create([
            'descricao' => $descricao,
            'codigo_peca' => $codigoPeca,
            'codigo_alternativo' => $codigoAlternativo,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'observacao' => $observacao,
        ]);
    }

    public function removePart(MaintenancePart $part): void
    {
        $order = $part->maintenanceOrder;

        if (! $order->statusEnum()->isOpen()) {
            throw new InvalidArgumentException('Não é possível remover peças de OS encerrada.');
        }

        $part->delete();
    }

    public function addLaborHour(
        MaintenanceOrder $order,
        string $descricaoAtividade,
        float $horas,
        ?CarbonInterface $data = null,
        ?User $technician = null,
    ): MaintenanceLaborHour {
        if (! $order->statusEnum()->isOpen()) {
            throw new InvalidArgumentException('Não é possível registrar horas em OS encerrada.');
        }

        if ($horas <= 0) {
            throw new InvalidArgumentException('Horas devem ser maiores que zero.');
        }

        return $order->laborHours()->create([
            'user_id' => ($technician ?? auth()->user())?->id,
            'data' => $data ?? now(),
            'horas' => $horas,
            'descricao_atividade' => $descricaoAtividade,
        ]);
    }

    public function removeLaborHour(MaintenanceLaborHour $hour): void
    {
        $order = $hour->maintenanceOrder;

        if (! $order->statusEnum()->isOpen()) {
            throw new InvalidArgumentException('Não é possível remover horas de OS encerrada.');
        }

        $hour->delete();
    }

    public function complete(
        MaintenanceOrder $order,
        ?string $solucaoAplicada = null,
        ?User $user = null,
    ): MaintenanceOrder {
        $user ??= auth()->user();

        if (! in_array($order->statusEnum(), [MaintenanceOrderStatus::EmExecucao, MaintenanceOrderStatus::AguardandoPeca], true)) {
            throw new InvalidArgumentException('OS deve estar em execução ou aguardando peça para concluir.');
        }

        return DB::transaction(function () use ($order, $solucaoAplicada, $user) {
            $before = ['status' => $order->status];

            $updates = [
                'status' => MaintenanceOrderStatus::Concluida->value,
                'solucao_aplicada' => $solucaoAplicada ?? $order->solucao_aplicada,
                'completed_at' => now(),
                'completed_by' => $user?->id,
            ];

            if ($order->tipoEnum() === MaintenanceOrderType::Preventiva) {
                $horimetro = $order->asset->fresh()->horimetro;
                if ($horimetro !== null) {
                    $updates['horimetro_servico'] = $horimetro;
                }
            }

            $order->update($updates);

            if (! $this->hasBlockingOrderForAsset($order->asset_id, $order->id)) {
                $this->assetStatusService->transition(
                    $order->asset,
                    AssetStatus::Disponivel,
                    "Conclusão OS {$order->codigo}",
                    $user,
                );
            }

            $this->auditStatusChange($order, $before, MaintenanceOrderStatus::Concluida, $user);

            return $order->fresh(['asset', 'parts', 'laborHours']);
        });
    }

    public function cancel(MaintenanceOrder $order, string $reason, ?User $user = null): MaintenanceOrder
    {
        $user ??= auth()->user();
        $this->assertStatus($order, MaintenanceOrderStatus::Aberta);

        if (blank($reason)) {
            throw new InvalidArgumentException('Motivo obrigatório para cancelar OS.');
        }

        return DB::transaction(function () use ($order, $reason, $user) {
            $before = ['status' => $order->status];

            $order->update([
                'status' => MaintenanceOrderStatus::Cancelada->value,
                'cancelled_at' => now(),
                'cancelled_by' => $user?->id,
                'cancel_reason' => $reason,
            ]);

            if (! $this->hasBlockingOrderForAsset($order->asset_id, $order->id)) {
                $this->tryReleaseAsset($order->asset, "Cancelamento OS {$order->codigo}", $user);
            }

            $this->auditStatusChange($order, $before, MaintenanceOrderStatus::Cancelada, $user);

            return $order->fresh(['asset']);
        });
    }

    public function hasBlockingOrderForAsset(int $assetId, ?int $exceptOrderId = null): bool
    {
        return MaintenanceOrder::query()
            ->where('asset_id', $assetId)
            ->where('impeditiva', true)
            ->open()
            ->when($exceptOrderId, fn ($q) => $q->where('id', '!=', $exceptOrderId))
            ->exists();
    }

    private function syncAssetToMaintenance(Asset $asset, string $motivo, ?User $user): void
    {
        $this->transitionAssetIfNeeded($asset, AssetStatus::EmManutencao, $motivo, $user);
    }

    private function transitionAssetIfNeeded(Asset $asset, AssetStatus $target, string $motivo, ?User $user): void
    {
        $current = $asset->statusEnum();

        if ($current === $target) {
            return;
        }

        if ($current->canTransitionTo($target)) {
            $this->assetStatusService->transition($asset, $target, $motivo, $user);
        }
    }

    private function tryReleaseAsset(Asset $asset, string $motivo, ?User $user): void
    {
        $current = $asset->statusEnum();

        if ($current->canTransitionTo(AssetStatus::Disponivel)) {
            $this->assetStatusService->transition($asset, AssetStatus::Disponivel, $motivo, $user);
        }
    }

    private function generateCodigo(): string
    {
        $next = (MaintenanceOrder::withoutGlobalScope('operating_company')->max('id') ?? 0) + 1;

        return 'OS-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $before */
    private function auditStatusChange(
        MaintenanceOrder $order,
        array $before,
        MaintenanceOrderStatus $newStatus,
        ?User $user,
    ): void {
        $this->auditService->log(
            AuditAction::StatusChanged,
            'MaintenanceOrder',
            $order->id,
            $before,
            ['status' => $newStatus->value],
            $user,
        );
    }

    private function assertStatus(MaintenanceOrder $order, MaintenanceOrderStatus $expected): void
    {
        if ($order->statusEnum() !== $expected) {
            throw new InvalidArgumentException("OS deve estar com status {$expected->label()}.");
        }
    }
}
