<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\User;
use App\Services\RentalBillingService;

class BillingCreateRenewalCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalBillingService $billingService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'billing.create_renewal';
    }

    public static function description(): string
    {
        return 'Gera pendência de renovação de ciclo na fila a faturar quando a locação está no vencimento.';
    }

    public function permission(): string
    {
        return 'finance.manage';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        return $this->affectedResourcesForRental($input);
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['rental_id', 'rental_codigo'],
            ],
            'properties' => [
                'rental_id' => ['type' => 'integer'],
                'rental_codigo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $entry = $this->billingService->createRenewalIfDue($rental, $user);

        if (! $entry) {
            return $this->failure(
                'Renovação ainda não está no vencimento ou já existe pendência aberta.',
                'business_rule',
            );
        }

        $rental = $rental->fresh();

        return $this->success(
            "Renovação **{$entry->codigo}** incluída na fila a faturar.",
            $this->contextBuilder->rental($rental),
            [
                ['label' => 'Abrir fila a faturar', 'url' => route('finance.billing-queue'), 'primary' => true],
                [
                    'label' => 'Autorizar',
                    'command' => 'billing.authorize_entry',
                    'params' => ['entry_id' => $entry->id],
                ],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);

        return AgentCommandResult::preview(
            "Simulação: gerar renovação de faturamento para **{$rental->codigo}** se estiver no vencimento.",
            ['rental_codigo' => $rental->codigo, 'next_billing_at' => $rental->next_billing_at?->toDateString()],
        );
    }
}
