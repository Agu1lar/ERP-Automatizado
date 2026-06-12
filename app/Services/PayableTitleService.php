<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CompanyType;
use App\Enums\PayableTitleOrigin;
use App\Enums\PayableTitleStatus;
use App\Enums\PaymentMethod;
use App\Models\Domain\Finance\PayableTitle;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\Domain\Person\Company;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayableTitleService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function createManual(
        Company $company,
        float $valor,
        CarbonInterface $vencimento,
        PayableTitleOrigin $origem = PayableTitleOrigin::Manual,
        ?string $observacoes = null,
        ?User $user = null,
    ): PayableTitle {
        $user ??= auth()->user();
        $this->assertPayableCompany($company, $origem);
        $valor = round($valor, 2);

        if ($valor <= 0) {
            throw new InvalidArgumentException('Valor deve ser maior que zero.');
        }

        return $this->storeTitle(
            $company,
            $origem,
            $valor,
            $vencimento,
            $observacoes,
            null,
            null,
            $user,
        );
    }

    public function createFromPurchaseOrder(
        PartPurchaseOrder $order,
        ?CarbonInterface $vencimento = null,
        ?User $user = null,
    ): PayableTitle {
        $user ??= auth()->user();
        $order->loadMissing(['items', 'supplier']);

        if (PayableTitle::query()->where('part_purchase_order_id', $order->id)->exists()) {
            throw new InvalidArgumentException('Este pedido já possui conta a pagar.');
        }

        $valor = $order->totalValue();
        if ($valor <= 0) {
            throw new InvalidArgumentException('Pedido sem valor para gerar conta a pagar.');
        }

        $due = $vencimento ?? now()->addDays(30);

        return $this->storeTitle(
            $order->supplier,
            PayableTitleOrigin::FornecedorPecas,
            $valor,
            $due,
            "Pedido de compra {$order->codigo}",
            $order->id,
            null,
            $user,
        );
    }

    public function createFromMaintenanceOrder(
        MaintenanceOrder $order,
        ?CarbonInterface $vencimento = null,
        ?User $user = null,
    ): PayableTitle {
        $user ??= auth()->user();
        $order->loadMissing('externalCompany');

        if ($order->payable_title_id) {
            throw new InvalidArgumentException('Esta OS já possui conta a pagar.');
        }

        if (! $order->external_company_id || ! $order->externalCompany) {
            throw new InvalidArgumentException('Informe a oficina externa na OS.');
        }

        $valor = round((float) ($order->valor_servico_externo ?? 0), 2);
        if ($valor <= 0) {
            throw new InvalidArgumentException('Informe o valor do serviço externo na OS.');
        }

        $this->assertPayableCompany($order->externalCompany, PayableTitleOrigin::OficinaExterna);

        $due = $vencimento ?? now()->addDays(15);

        return DB::transaction(function () use ($order, $valor, $due, $user) {
            $title = $this->storeTitle(
                $order->externalCompany,
                PayableTitleOrigin::OficinaExterna,
                $valor,
                $due,
                "Serviço externo — OS {$order->codigo}",
                null,
                $order->id,
                $user,
            );

            $order->update(['payable_title_id' => $title->id]);

            return $title->fresh(['company', 'maintenanceOrder']);
        });
    }

    public function markAsPaid(
        PayableTitle $title,
        PaymentMethod $method,
        ?string $observacoes = null,
        ?CarbonInterface $paidAt = null,
        ?User $user = null,
    ): PayableTitle {
        $user ??= auth()->user();

        if ($title->statusEnum() !== PayableTitleStatus::Aberto) {
            throw new InvalidArgumentException('Somente títulos abertos podem receber baixa.');
        }

        $paidAt ??= now();
        $before = ['status' => $title->status, 'valor' => $title->valor];

        $title->update([
            'status' => PayableTitleStatus::Pago->value,
            'forma_pagamento' => $method->value,
            'pago_em' => $paidAt,
            'pago_por' => $user?->id,
            'observacoes_pagamento' => $observacoes,
        ]);

        $this->auditService->log(
            AuditAction::Updated,
            'PayableTitle',
            $title->id,
            $before,
            $title->fresh()->toArray(),
            $user,
        );

        return $title->fresh(['company']);
    }

    public function cancel(PayableTitle $title, ?string $reason = null, ?User $user = null): PayableTitle
    {
        $user ??= auth()->user();

        if ($title->statusEnum() === PayableTitleStatus::Pago) {
            throw new InvalidArgumentException('Título pago não pode ser cancelado.');
        }

        $before = ['status' => $title->status];

        $title->update([
            'status' => PayableTitleStatus::Cancelado->value,
            'observacoes' => trim(($title->observacoes ?? '').($reason ? "\nCancelado: {$reason}" : '')),
        ]);

        $this->auditService->log(
            AuditAction::Updated,
            'PayableTitle',
            $title->id,
            $before,
            ['status' => PayableTitleStatus::Cancelado->value],
            $user,
        );

        return $title->fresh();
    }

    /** @return \Illuminate\Support\Collection<int, Company> */
    public function supplierOptions(PayableTitleOrigin $origem): \Illuminate\Support\Collection
    {
        $types = match ($origem) {
            PayableTitleOrigin::FornecedorPecas => [CompanyType::Fornecedor->value],
            PayableTitleOrigin::OficinaExterna => [CompanyType::Externa->value],
            PayableTitleOrigin::Manual => [CompanyType::Fornecedor->value, CompanyType::Externa->value],
        };

        return Company::query()
            ->where('ativo', true)
            ->whereIn('tipo', $types)
            ->orderBy('nome')
            ->get();
    }

    public function openBalance(): float
    {
        return (float) PayableTitle::query()->open()->sum('valor');
    }

    private function storeTitle(
        Company $company,
        PayableTitleOrigin $origem,
        float $valor,
        CarbonInterface $vencimento,
        ?string $observacoes,
        ?int $purchaseOrderId,
        ?int $maintenanceOrderId,
        ?User $user,
    ): PayableTitle {
        $this->assertPayableCompany($company, $origem);

        $title = PayableTitle::create([
            'codigo' => $this->generateCodigo(),
            'company_id' => $company->id,
            'part_purchase_order_id' => $purchaseOrderId,
            'maintenance_order_id' => $maintenanceOrderId,
            'origem' => $origem->value,
            'valor' => $valor,
            'vencimento' => $vencimento->copy()->startOfDay(),
            'status' => PayableTitleStatus::Aberto->value,
            'observacoes' => $observacoes,
        ]);

        $this->auditService->log(
            AuditAction::Created,
            'PayableTitle',
            $title->id,
            null,
            $title->toArray(),
            $user,
        );

        return $title->fresh(['company']);
    }

    private function assertPayableCompany(Company $company, PayableTitleOrigin $origem): void
    {
        $allowed = match ($origem) {
            PayableTitleOrigin::FornecedorPecas => [CompanyType::Fornecedor->value],
            PayableTitleOrigin::OficinaExterna => [CompanyType::Externa->value],
            PayableTitleOrigin::Manual => [CompanyType::Fornecedor->value, CompanyType::Externa->value],
        };

        if (! in_array($company->tipo, $allowed, true)) {
            throw new InvalidArgumentException('Empresa não permitida para este tipo de conta a pagar.');
        }
    }

    private function generateCodigo(): string
    {
        $prefix = 'PAG-'.now()->format('ym');
        $last = PayableTitle::query()
            ->withoutGlobalScope('operating_company')
            ->where('codigo', 'like', $prefix.'%')
            ->orderByDesc('codigo')
            ->value('codigo');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
