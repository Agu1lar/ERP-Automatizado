<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Enums\RentalBillingQueueStatus;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class BillingGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'billing.get';
    }

    public static function description(): string
    {
        return 'Retorna detalhes de uma pendência na fila a faturar (FAT-…).';
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['entry_id', 'entry_codigo'],
            ],
            'properties' => [
                'entry_id' => ['type' => 'integer'],
                'entry_codigo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $entry = $this->resolveBillingEntry($input);
        $entry->load(['customer', 'rental.asset', 'receivableTitle']);

        $status = RentalBillingQueueStatus::tryFrom($entry->status)?->label() ?? $entry->status;

        $nextSteps = match ($entry->statusEnum()) {
            RentalBillingQueueStatus::Pendente => [
                ['label' => 'Autorizar', 'command' => 'billing.authorize_entry', 'params' => ['entry_id' => $entry->id], 'primary' => true],
            ],
            RentalBillingQueueStatus::Autorizado => [
                ['label' => 'Gerar fatura', 'command' => 'billing.invoice_entry', 'params' => ['entry_id' => $entry->id], 'primary' => true],
            ],
            default => [
                ['label' => 'Abrir fila', 'url' => CopilotNavigationLinks::billingQueue(), 'primary' => true],
            ],
        };

        return $this->success(
            "Pendência **{$entry->codigo}** — {$status}, **R$ ".number_format((float) $entry->valor_car, 2, ',', '.').'**.',
            [
                'entity' => 'billing_entry',
                'entry' => [
                    'id' => $entry->id,
                    'codigo' => $entry->codigo,
                    'tipo' => $entry->tipo,
                    'status' => $entry->status,
                    'valor_car' => (float) $entry->valor_car,
                    'gerado_em' => $entry->gerado_em?->toIso8601String(),
                    'customer_nome' => $entry->customer?->nome,
                    'rental_codigo' => $entry->rental?->codigo,
                    'title_codigo' => $entry->receivableTitle?->codigo,
                ],
                'rental' => $entry->rental
                    ? $this->contextBuilder->rental($entry->rental)
                    : null,
            ],
            $nextSteps,
        );
    }
}
