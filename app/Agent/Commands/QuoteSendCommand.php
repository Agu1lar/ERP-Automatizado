<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalQuoteStatus;
use App\Models\User;
use App\Services\RentalQuoteService;

class QuoteSendCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalQuoteService $quoteService,
    ) {}

    public static function name(): string
    {
        return 'quote.send';
    }

    public static function description(): string
    {
        return 'Envia um orçamento em rascunho, definindo validade em dias.';
    }

    public function permission(): string
    {
        return 'rentals.reserve';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        try {
            $quote = $this->resolveQuote($input);

            return array_values(array_filter([
                ['type' => 'asset', 'id' => (int) $quote->asset_id],
                ['type' => 'customer', 'id' => (int) $quote->customer_id],
            ]));
        } catch (\Throwable) {
            return [];
        }
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
                'validity_days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 90],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $quote = $this->resolveQuote($input);
        $days = min(max((int) ($input['validity_days'] ?? 7), 1), 90);
        $quote = $this->quoteService->send($quote, $days, $user);

        return $this->success(
            "Orçamento **{$quote->codigo}** enviado — válido até {$quote->valid_until->format('d/m/Y')}.",
            ['entity' => 'rental_quote', 'quote' => [
                'id' => $quote->id,
                'codigo' => $quote->codigo,
                'status' => $quote->status,
                'valid_until' => $quote->valid_until?->toDateString(),
            ]],
            [
                ['label' => 'Converter em reserva', 'command' => 'quote.convert', 'params' => ['quote_id' => $quote->id], 'primary' => true],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $quote = $this->resolveQuote($input);

        if ($quote->statusEnum() !== RentalQuoteStatus::Rascunho) {
            return $this->failure('Somente rascunhos podem ser enviados.', 'business_rule');
        }

        return AgentCommandResult::preview(
            "Simulação: enviar orçamento **{$quote->codigo}**.",
            ['quote_codigo' => $quote->codigo],
        );
    }
}
