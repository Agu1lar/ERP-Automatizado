<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DocumentExportCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public static function name(): string
    {
        return 'document.export';
    }

    public static function description(): string
    {
        return 'Gera URL de download de PDF: resumo/contrato/demonstrativo de locação, ficha de patrimônio, OS de manutenção ou fatura da fila.';
    }

    public function permission(): string
    {
        return 'rentals.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['document_type'],
            'properties' => [
                'document_type' => [
                    'type' => 'string',
                    'enum' => [
                        'rental_summary',
                        'rental_contract',
                        'rental_statement',
                        'asset_sheet',
                        'maintenance_order',
                        'billing_invoice',
                    ],
                ],
                'rental_id' => ['type' => 'integer'],
                'rental_codigo' => ['type' => 'string'],
                'periodo_de' => ['type' => 'string', 'format' => 'date'],
                'periodo_ate' => ['type' => 'string', 'format' => 'date'],
                'asset_id' => ['type' => 'integer'],
                'asset_codigo' => ['type' => 'string'],
                'order_id' => ['type' => 'integer'],
                'order_codigo' => ['type' => 'string'],
                'entry_id' => ['type' => 'integer'],
                'entry_codigo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $type = (string) $input['document_type'];

        return match ($type) {
            'rental_summary' => $this->rentalSummary($input, $user),
            'rental_contract' => $this->rentalContract($input, $user),
            'rental_statement' => $this->rentalStatement($input, $user),
            'asset_sheet' => $this->assetSheet($input, $user),
            'maintenance_order' => $this->maintenanceOrder($input, $user),
            'billing_invoice' => $this->billingInvoice($input, $user),
            default => $this->failure('Tipo de documento inválido.', 'validation_failed'),
        };
    }

    /** @param  array<string, mixed>  $input */
    private function rentalSummary(array $input, User $user): AgentCommandResult
    {
        $this->assertCan($user, 'rentals.view');
        $rental = $this->resolveRental($input);
        $this->authorizeModel($user, 'view', $rental);

        $url = route('rentals.pdf', $rental);

        return $this->success(
            "PDF do resumo da locação **{$rental->codigo}** pronto para abrir.",
            $this->exportPayload('rental_summary', $rental->codigo, $url, [
                'rental_id' => $rental->id,
            ]),
            $this->pdfNextStep('Resumo da locação', $url),
        );
    }

    /** @param  array<string, mixed>  $input */
    private function rentalContract(array $input, User $user): AgentCommandResult
    {
        $this->assertCan($user, 'rentals.view');
        $rental = $this->resolveRental($input);
        $this->authorizeModel($user, 'view', $rental);

        $url = route('rentals.contract.pdf', $rental);

        return $this->success(
            "PDF do contrato da locação **{$rental->codigo}** pronto para abrir.",
            $this->exportPayload('rental_contract', $rental->codigo, $url, [
                'rental_id' => $rental->id,
            ]),
            $this->pdfNextStep('Contrato de locação', $url),
        );
    }

    /** @param  array<string, mixed>  $input */
    private function rentalStatement(array $input, User $user): AgentCommandResult
    {
        $this->assertCan($user, 'rentals.view');
        $rental = $this->resolveRental($input);
        $this->authorizeModel($user, 'view', $rental);

        $de = $input['periodo_de'] ?? $rental->checkout_at?->toDateString()
            ?? $rental->reserved_at->toDateString();
        $ate = $input['periodo_ate'] ?? $rental->expected_return_at?->toDateString() ?? now()->toDateString();

        $url = route('rentals.statement.pdf', [
            'rental' => $rental,
            'de' => $de,
            'ate' => $ate,
        ]);

        return $this->success(
            "Demonstrativo da locação **{$rental->codigo}** ({$de} a {$ate}) pronto para abrir.",
            $this->exportPayload('rental_statement', $rental->codigo, $url, [
                'rental_id' => $rental->id,
                'periodo_de' => $de,
                'periodo_ate' => $ate,
            ]),
            $this->pdfNextStep('Demonstrativo de locação', $url),
        );
    }

    /** @param  array<string, mixed>  $input */
    private function assetSheet(array $input, User $user): AgentCommandResult
    {
        $this->assertCan($user, 'fleet.assets.view');
        $asset = $this->resolveAsset($input);
        $this->authorizeModel($user, 'view', $asset);

        $url = route('assets.pdf', $asset);

        return $this->success(
            "PDF da ficha do patrimônio **{$asset->codigo_patrimonio}** pronto para abrir.",
            $this->exportPayload('asset_sheet', $asset->codigo_patrimonio, $url, [
                'asset_id' => $asset->id,
            ]),
            $this->pdfNextStep('Ficha patrimônio', $url),
        );
    }

    /** @param  array<string, mixed>  $input */
    private function maintenanceOrder(array $input, User $user): AgentCommandResult
    {
        $this->assertCan($user, 'maintenance.view');
        $order = $this->resolveMaintenanceOrder($input);
        $this->authorizeModel($user, 'view', $order);

        $url = route('maintenance.pdf', $order);

        return $this->success(
            "PDF da OS **{$order->codigo}** pronto para abrir.",
            $this->exportPayload('maintenance_order', $order->codigo, $url, [
                'order_id' => $order->id,
            ]),
            $this->pdfNextStep('OS manutenção', $url),
        );
    }

    /** @param  array<string, mixed>  $input */
    private function billingInvoice(array $input, User $user): AgentCommandResult
    {
        $this->assertCan($user, 'finance.view');
        $entry = $this->resolveBillingEntry($input);

        $url = route('finance.billing.pdf', $entry);

        return $this->success(
            "PDF da fatura **{$entry->codigo}** pronto para abrir.",
            $this->exportPayload('billing_invoice', $entry->codigo, $url, [
                'entry_id' => $entry->id,
            ]),
            $this->pdfNextStep('Fatura', $url),
        );
    }

    /** @param  array<string, mixed>  $meta */
    private function exportPayload(string $type, string $reference, string $url, array $meta = []): array
    {
        return [
            'entity' => 'document_export',
            'document_type' => $type,
            'reference' => $reference,
            'pdf_url' => $url,
            ...$meta,
        ];
    }

    /** @return list<array{label: string, url: string, primary?: bool}> */
    private function pdfNextStep(string $label, string $url): array
    {
        return [
            ['label' => "Abrir PDF — {$label}", 'url' => $url, 'primary' => true],
        ];
    }

    private function assertCan(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException('Sem permissão para este tipo de documento.');
        }
    }

    private function authorizeModel(User $user, string $ability, object $model): void
    {
        if (! $user->can($ability, $model)) {
            throw new AuthorizationException('Sem permissão para acessar este registro.');
        }
    }
}
