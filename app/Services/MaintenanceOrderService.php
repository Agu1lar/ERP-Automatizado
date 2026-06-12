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
    /** @var array<string, string> */
    public const CHECKLIST_CAMPO = [
        'acesso_obra' => 'Acesso à obra liberado',
        'equipamento_no_local' => 'Equipamento identificado no local',
        'servico_executado' => 'Serviço executado conforme solicitado',
        'area_limpa' => 'Área de trabalho deixada em ordem',
    ];

    public function __construct(
        private readonly AssetStatusService $assetStatusService,
        private readonly AuditService $auditService,
        private readonly PartCatalogService $partCatalogService,
        private readonly PartStockService $partStockService,
        private readonly MaintenanceIndemnityService $maintenanceIndemnityService,
        private readonly PayableTitleService $payableTitleService,
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

            $this->syncAssetForOrderType($asset, $tipo, "Abertura OS {$order->codigo}", $user);

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

    public function openField(
        Asset $asset,
        string $descricaoProblema,
        ?Rental $rental = null,
        ?User $user = null,
    ): MaintenanceOrder {
        $user ??= auth()->user();
        $rental ??= $asset->activeRental();

        if ($rental === null || $rental->statusEnum() !== \App\Enums\RentalStatus::Locado) {
            throw new InvalidArgumentException('Manutenção em campo exige patrimônio com locação ativa (status Locado).');
        }

        if (! in_array($asset->statusEnum(), [AssetStatus::Locado, AssetStatus::EmManutencaoCampo], true)) {
            throw new InvalidArgumentException('Patrimônio deve estar locado ou em manutenção em campo.');
        }

        return $this->open(
            $asset,
            $descricaoProblema,
            MaintenanceOrderType::Campo,
            MaintenancePriority::Normal,
            false,
            null,
            $user?->id,
            $rental,
            'OS aberta pelo técnico em campo.',
            $user,
        );
    }

    /**
     * @param  array<string, bool>  $checklist
     */
    public function completeField(
        MaintenanceOrder $order,
        array $checklist,
        ?string $solucaoAplicada = null,
        ?float $horimetro = null,
        ?User $user = null,
    ): MaintenanceOrder {
        if ($order->tipoEnum() !== MaintenanceOrderType::Campo) {
            throw new InvalidArgumentException('Esta ação é apenas para OS de manutenção em campo.');
        }

        $this->validateChecklistItems(self::CHECKLIST_CAMPO, $checklist);

        if ($order->statusEnum() === MaintenanceOrderStatus::Aberta) {
            $order = $this->start($order, $user);
        }

        if ($horimetro !== null) {
            $order->asset->update(['horimetro' => $horimetro]);
        }

        return $this->complete(
            $order,
            $solucaoAplicada ?? 'Serviço em campo concluído conforme checklist.',
            $user,
        );
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
                $this->executionAssetStatus($order),
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
                $this->executionAssetStatus($order),
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
        ?float $valorIndenizacao = null,
        ?int $externalCompanyId = null,
        ?float $valorServicoExterno = null,
        bool $includeExternalFields = false,
    ): MaintenanceOrder {
        if (! $order->statusEnum()->isOpen()) {
            throw new InvalidArgumentException('OS encerrada não pode ser editada.');
        }

        $updates = array_filter([
            'diagnostico' => $diagnostico,
            'solucao_aplicada' => $solucaoAplicada,
            'assigned_to' => $assignedTo,
            'expected_completion_at' => $expectedCompletion,
            'parecer_tecnico' => $parecerTecnico,
            'customer_id' => $customerId,
            'assinatura_caixa' => $assinaturaCaixa,
            'assinatura_orcado_por' => $assinaturaOrcadoPor,
            'assinatura_montado_por' => $assinaturaMontadoPor,
            'valor_indenizacao' => $valorIndenizacao,
        ], fn ($value) => $value !== null);

        if ($includeExternalFields) {
            $updates['external_company_id'] = $externalCompanyId;
            $updates['valor_servico_externo'] = $valorServicoExterno;
        }

        $order->update($updates);

        if ($assetVoltagem !== null) {
            $order->asset->update(['voltagem' => $assetVoltagem ?: null]);
        }

        return $order->fresh(['asset', 'customer', 'rental.customer', 'externalCompany', 'payableTitle']);
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

        $catalogItem = filled($codigoPeca)
            ? $this->partCatalogService->findByCode($codigoPeca)
            : null;

        return $order->parts()->create([
            'part_catalog_item_id' => $catalogItem?->id,
            'descricao' => $descricao,
            'codigo_peca' => $codigoPeca,
            'codigo_alternativo' => $codigoAlternativo,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario ?? $catalogItem?->valor_unitario_padrao,
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
            $order = $order->fresh(['parts.catalogItem', 'rental.customer', 'customer', 'receivableTitle']);
            $this->maintenanceIndemnityService->assertCanComplete($order);
            $this->partStockService->deductForCompletedOrder($order, $user);

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
                $this->releaseAssetAfterComplete($order, $user);
            }

            $this->auditStatusChange($order, $before, MaintenanceOrderStatus::Concluida, $user);

            $order = $order->fresh(['asset', 'parts', 'laborHours', 'receivableTitle', 'externalCompany', 'payableTitle']);
            $this->maintenanceIndemnityService->ensureReceivableTitle($order, $user);
            $this->ensureExternalPayableTitle($order, $user);

            return $order->fresh(['asset', 'parts', 'laborHours', 'receivableTitle', 'externalCompany', 'payableTitle']);
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

    private function syncAssetForOrderType(
        Asset $asset,
        MaintenanceOrderType $tipo,
        string $motivo,
        ?User $user,
    ): void {
        if ($tipo->isField() && $asset->statusEnum() === AssetStatus::Locado) {
            $this->transitionAssetIfNeeded($asset, AssetStatus::EmManutencaoCampo, $motivo, $user);

            return;
        }

        $this->syncAssetToMaintenance($asset, $motivo, $user);
    }

    private function executionAssetStatus(MaintenanceOrder $order): AssetStatus
    {
        return $order->tipoEnum()->isField()
            ? AssetStatus::EmManutencaoCampo
            : AssetStatus::EmManutencao;
    }

    private function releaseAssetAfterComplete(MaintenanceOrder $order, ?User $user): void
    {
        $asset = $order->asset->fresh();
        $target = AssetStatus::Disponivel;

        if ($order->tipoEnum()->isField()) {
            $activeRental = $asset->activeRental();
            if ($activeRental?->statusEnum() === \App\Enums\RentalStatus::Locado) {
                $target = AssetStatus::Locado;
            }
        }

        if ($asset->statusEnum()->canTransitionTo($target)) {
            $this->assetStatusService->transition($asset, $target, "Conclusão OS {$order->codigo}", $user);
        }
    }

    /** @param  array<string, string>  $expected */
    private function validateChecklistItems(array $expected, array $checkedItems): void
    {
        foreach (array_keys($expected) as $key) {
            if (empty($checkedItems[$key])) {
                throw new InvalidArgumentException('Conclua todos os itens do checklist antes de finalizar.');
            }
        }
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

    private function ensureExternalPayableTitle(MaintenanceOrder $order, ?User $user): void
    {
        if ($order->payable_title_id) {
            return;
        }

        if (! $order->external_company_id || (float) ($order->valor_servico_externo ?? 0) <= 0) {
            return;
        }

        try {
            $this->payableTitleService->createFromMaintenanceOrder($order, now()->addDays(15), $user);
        } catch (InvalidArgumentException) {
            // Dados incompletos ou empresa inválida — não bloqueia conclusão da OS.
        }
    }
}
