<?php

namespace App\Agent\Commands;

use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class QuoteGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'quote.get';
    }

    public static function description(): string
    {
        return 'Retorna o contexto completo de um orçamento/pré-reserva.';
    }

    public function permission(): string
    {
        return 'rentals.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['quote_id', 'quote_codigo'],
            ],
            'properties' => [
                'quote_id' => ['type' => 'integer'],
                'quote_codigo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $quote = $this->resolveQuote($input);
        $context = $this->contextBuilder->quote($quote);

        return $this->success(
            "Orçamento **{$quote->codigo}** — {$quote->statusEnum()->label()}.",
            $context,
            [
                ['label' => 'Abrir orçamentos', 'url' => $context['urls']['lista'] ?? CopilotNavigationLinks::quotes(), 'primary' => true],
            ],
        );
    }
}
