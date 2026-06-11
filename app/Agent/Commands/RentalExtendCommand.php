<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalStatus;
use App\Models\User;
use App\Services\RentalService;
use Carbon\Carbon;

class RentalExtendCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalService $rentalService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'rental.extend';
    }

    public static function description(): string
    {
        return 'Prorroga a previsão de retorno de uma locação locada e recalcula o valor.';
    }

    public function permission(): string
    {
        return 'rentals.operate';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        return $this->affectedResourcesForRental($input);
    }

    protected function declaredResourceTypes(): array
    {
        return ['rental', 'asset'];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['rental_id', 'rental_codigo'],
            ],
            'required' => ['new_expected_return_at'],
            'properties' => [
                'rental_id' => ['type' => 'integer'],
                'rental_codigo' => ['type' => 'string'],
                'new_expected_return_at' => ['type' => 'string', 'format' => 'date'],
                'pricing_period' => ['type' => 'string', 'enum' => ['diaria', 'semanal', 'mensal']],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $newDate = Carbon::parse($input['new_expected_return_at']);
        $period = ! empty($input['pricing_period'])
            ? RentalPricingPeriod::from($input['pricing_period'])
            : null;

        $rental = $this->rentalService->extend($rental, $newDate, $period, $user);

        return $this->success(
            "Locação **{$rental->codigo}** prorrogada até **{$rental->expected_return_at->format('d/m/Y')}**.",
            $this->contextBuilder->rental($rental),
            [
                ['label' => 'Abrir locação', 'url' => route('rentals.show', $rental), 'primary' => true],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $newDate = Carbon::parse($input['new_expected_return_at']);

        if ($rental->statusEnum() !== RentalStatus::Locado) {
            return $this->failure('Somente locações locadas podem ser prorrogadas.', 'business_rule');
        }

        if ($rental->expected_return_at === null || $newDate->startOfDay()->lte($rental->expected_return_at)) {
            return $this->failure('Nova data deve ser posterior ao vencimento atual.', 'business_rule');
        }

        return AgentCommandResult::preview(
            "Simulação: prorrogar **{$rental->codigo}** de {$rental->expected_return_at->format('d/m/Y')} para {$newDate->format('d/m/Y')}.",
            [
                'rental_codigo' => $rental->codigo,
                'expected_return_atual' => $rental->expected_return_at->toDateString(),
                'expected_return_novo' => $newDate->toDateString(),
            ],
        );
    }
}
