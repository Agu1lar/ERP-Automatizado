<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalStatus;
use App\Models\User;
use App\Services\RentalService;

class RentalCancelCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalService $rentalService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'rental.cancel';
    }

    public static function description(): string
    {
        return 'Cancela uma locação em status reservado (libera o patrimônio).';
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
            'required' => ['reason'],
            'properties' => [
                'rental_id' => ['type' => 'integer'],
                'rental_codigo' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $reason = trim((string) ($input['reason'] ?? ''));

        $rental = $this->rentalService->cancel($rental, $reason, $user);

        return $this->success(
            "Reserva **{$rental->codigo}** cancelada.",
            $this->contextBuilder->rental($rental),
            [
                ['label' => 'Ver locações', 'url' => route('rentals.index'), 'primary' => true],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $reason = trim((string) ($input['reason'] ?? ''));

        if ($rental->statusEnum() !== RentalStatus::Reservado) {
            return $this->failure('Somente locações reservadas podem ser canceladas.', 'business_rule');
        }

        if ($reason === '') {
            return $this->failure('Informe o motivo do cancelamento.', 'validation_failed');
        }

        return AgentCommandResult::preview(
            "Simulação: cancelar reserva **{$rental->codigo}** — motivo: {$reason}.",
            [
                'rental_codigo' => $rental->codigo,
                'status_atual' => $rental->status,
                'status_novo' => RentalStatus::Cancelado->value,
                'asset_codigo' => $rental->asset?->codigo_patrimonio,
            ],
        );
    }
}
