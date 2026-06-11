<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalPricingPeriod;
use App\Models\User;
use App\Services\RentalQuoteService;
use Carbon\Carbon;

class QuoteCreateCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalQuoteService $quoteService,
    ) {}

    public static function name(): string
    {
        return 'quote.create';
    }

    public static function description(): string
    {
        return 'Cria um orçamento/pré-reserva em rascunho para patrimônio e cliente.';
    }

    public function permission(): string
    {
        return 'rentals.reserve';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        try {
            $asset = $this->resolveAsset($input);
            $customer = $this->resolveCustomer($input);

            return [
                ['type' => 'asset', 'id' => $asset->id],
                ['type' => 'customer', 'id' => $customer->id],
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['asset_id', 'asset_codigo'],
                ['customer_id', 'customer_cpf_cnpj', 'customer_name'],
            ],
            'properties' => [
                'asset_id' => ['type' => 'integer'],
                'asset_codigo' => ['type' => 'string'],
                'customer_id' => ['type' => 'integer'],
                'customer_cpf_cnpj' => ['type' => 'string'],
                'customer_name' => ['type' => 'string'],
                'expected_return_at' => ['type' => 'string', 'format' => 'date'],
                'local_obra' => ['type' => 'string'],
                'observacoes' => ['type' => 'string'],
                'pricing_period' => ['type' => 'string', 'enum' => ['diaria', 'semanal', 'mensal']],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $asset = $this->resolveAsset($input);
        $customer = $this->resolveCustomer($input);
        $period = ! empty($input['pricing_period'])
            ? RentalPricingPeriod::from($input['pricing_period'])
            : null;

        $quote = $this->quoteService->create(
            $asset,
            $customer,
            ! empty($input['expected_return_at']) ? Carbon::parse($input['expected_return_at']) : null,
            $input['local_obra'] ?? null,
            $input['observacoes'] ?? null,
            $period,
            $user,
        );

        return $this->success(
            "Orçamento **{$quote->codigo}** criado em rascunho.",
            ['entity' => 'rental_quote', 'quote' => ['id' => $quote->id, 'codigo' => $quote->codigo, 'status' => $quote->status]],
            [
                ['label' => 'Enviar orçamento', 'command' => 'quote.send', 'params' => ['quote_id' => $quote->id], 'primary' => true],
                ['label' => 'Ver orçamentos', 'url' => route('quotes.index')],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        return AgentCommandResult::preview(
            'Simulação: criar orçamento para patrimônio e cliente informados.',
            ['entity' => 'rental_quote', 'dry_run' => true],
        );
    }
}
