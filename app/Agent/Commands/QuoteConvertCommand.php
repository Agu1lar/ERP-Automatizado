<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\User;
use App\Services\RentalQuoteService;

class QuoteConvertCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalQuoteService $quoteService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'quote.convert';
    }

    public static function description(): string
    {
        return 'Converte um orçamento enviado e válido em reserva de locação.';
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

    protected function declaredResourceTypes(): array
    {
        return ['asset', 'customer', 'rental'];
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
        $rental = $this->quoteService->convertToReservation($quote, $user);

        return $this->success(
            "Orçamento **{$quote->codigo}** convertido em reserva **{$rental->codigo}**.",
            $this->contextBuilder->rental($rental),
            [
                ['label' => 'Abrir locação', 'url' => route('rentals.show', $rental), 'primary' => true],
                [
                    'label' => 'Registrar saída',
                    'command' => 'rental.checkout',
                    'params' => ['rental_id' => $rental->id],
                ],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $quote = $this->resolveQuote($input);
        $quote->load(['asset', 'customer']);

        if (! $quote->statusEnum()->canConvert()) {
            return $this->failure('Somente orçamentos enviados podem ser convertidos.', 'business_rule');
        }

        if ($quote->isExpired()) {
            return $this->failure('Orçamento expirado.', 'business_rule');
        }

        return AgentCommandResult::preview(
            "Simulação: converter **{$quote->codigo}** em reserva para {$quote->customer?->nome} — patrimônio {$quote->asset?->codigo_patrimonio}.",
            [
                'quote' => ['id' => $quote->id, 'codigo' => $quote->codigo],
                'customer_nome' => $quote->customer?->nome,
                'asset_codigo' => $quote->asset?->codigo_patrimonio,
            ],
        );
    }
}
