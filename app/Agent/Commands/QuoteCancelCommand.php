<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\User;
use App\Services\RentalQuoteService;

class QuoteCancelCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalQuoteService $quoteService,
    ) {}

    public static function name(): string
    {
        return 'quote.cancel';
    }

    public static function description(): string
    {
        return 'Cancela um orçamento que ainda não foi convertido.';
    }

    public function permission(): string
    {
        return 'rentals.reserve';
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

    public function execute(array $input, User $user): AgentCommandResult
    {
        $quote = $this->resolveQuote($input);
        $codigo = $quote->codigo;
        $quote = $this->quoteService->cancel($quote, $user);

        return $this->success(
            "Orçamento **{$codigo}** cancelado.",
            ['entity' => 'rental_quote', 'quote' => ['id' => $quote->id, 'codigo' => $codigo, 'status' => $quote->status]],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $quote = $this->resolveQuote($input);

        return AgentCommandResult::preview(
            "Simulação: cancelar orçamento **{$quote->codigo}**.",
            ['quote_codigo' => $quote->codigo, 'status_atual' => $quote->status],
        );
    }
}
