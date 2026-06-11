<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalBillingQueueStatus;
use App\Models\User;
use App\Services\RentalBillingService;

class BillingProcessCustomerPendingCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalBillingService $billingService,
    ) {}

    public static function name(): string
    {
        return 'billing.process_customer_pending';
    }

    public static function description(): string
    {
        return 'Autoriza e/ou fatura todas as pendências da fila para um cliente específico.';
    }

    public function permission(): string
    {
        return 'finance.manage';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['customer_id', 'customer_cpf_cnpj', 'customer_name'],
            ],
            'properties' => [
                'customer_id' => ['type' => 'integer'],
                'customer_cpf_cnpj' => ['type' => 'string'],
                'customer_name' => ['type' => 'string'],
                'action' => [
                    'type' => 'string',
                    'enum' => ['authorize', 'invoice', 'authorize_and_invoice'],
                ],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $customer = $this->resolveCustomer($input);
        $action = $this->resolveAction($input);
        $entries = $this->billingService->pendingEntriesForCustomer($customer->id);

        if ($entries->isEmpty()) {
            return $this->success("Nenhuma pendência a faturar para {$customer->nome}.", [
                'customer' => ['id' => $customer->id, 'nome' => $customer->nome],
                'processed' => 0,
            ]);
        }

        $processed = match ($action) {
            'authorize' => $this->billingService->authorizeEntries($entries, $user),
            'invoice', 'authorize_and_invoice' => $this->billingService->invoiceEntries($entries, $user),
        };

        $total = round((float) $processed->sum('valor_car'), 2);
        $verb = $action === 'authorize' ? 'autorizada(s)' : 'faturada(s)';

        return $this->success(
            "{$processed->count()} pendência(s) {$verb} para {$customer->nome} — total R$ ".number_format($total, 2, ',', '.').'.',
            [
                'customer' => ['id' => $customer->id, 'nome' => $customer->nome],
                'action' => $action,
                'processed' => $processed->count(),
                'total_car' => $total,
                'entries' => $processed->map(fn ($e) => [
                    'id' => $e->id,
                    'codigo' => $e->codigo,
                    'status' => $e->status,
                    'valor_car' => (float) $e->valor_car,
                ])->all(),
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $customer = $this->resolveCustomer($input);
        $action = $this->resolveAction($input);
        $entries = $this->billingService->pendingEntriesForCustomer($customer->id);

        if ($entries->isEmpty()) {
            return AgentCommandResult::preview(
                "Simulação: nenhuma pendência a faturar para {$customer->nome}.",
                ['customer' => ['id' => $customer->id, 'nome' => $customer->nome], 'processed' => 0],
            );
        }

        $total = round((float) $entries->sum('valor_car'), 2);
        $verb = $action === 'authorize' ? 'autorizadas' : 'faturadas';

        return AgentCommandResult::preview(
            "Simulação: {$entries->count()} pendência(s) seriam {$verb} para {$customer->nome} — total R$ ".number_format($total, 2, ',', '.').'.',
            [
                'customer' => ['id' => $customer->id, 'nome' => $customer->nome],
                'action' => $action,
                'would_process' => $entries->count(),
                'total_car' => $total,
                'entries' => $entries->map(fn ($e) => [
                    'id' => $e->id,
                    'codigo' => $e->codigo,
                    'status_atual' => $e->status,
                    'status_novo' => $action === 'authorize'
                        ? RentalBillingQueueStatus::Autorizado->value
                        : RentalBillingQueueStatus::Faturado->value,
                    'valor_car' => (float) $e->valor_car,
                    'rental_codigo' => $e->rental?->codigo,
                ])->all(),
            ],
        );
    }

    /** @param  array<string, mixed>  $input */
    private function resolveAction(array $input): string
    {
        $action = $input['action'] ?? 'authorize_and_invoice';

        if (! in_array($action, ['authorize', 'invoice', 'authorize_and_invoice'], true)) {
            return 'authorize_and_invoice';
        }

        return $action;
    }
}
