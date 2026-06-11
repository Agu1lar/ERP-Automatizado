<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalStatus;
use App\Models\User;
use App\Services\RentalService;

class RentalSubstituteAssetCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalService $rentalService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'rental.substitute';
    }

    public static function description(): string
    {
        return 'Substitui o patrimônio de uma locação reservada ou locada por outro disponível.';
    }

    public function permission(): string
    {
        return 'rentals.operate';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        try {
            $rental = $this->resolveRental($input);
            $newAsset = $this->resolveAsset([
                'asset_id' => $input['new_asset_id'] ?? null,
                'asset_codigo' => $input['new_asset_codigo'] ?? null,
            ]);

            $resources = [
                ['type' => 'rental', 'id' => $rental->id],
                ['type' => 'asset', 'id' => (int) $rental->asset_id],
                ['type' => 'asset', 'id' => $newAsset->id],
            ];

            return $resources;
        } catch (\Throwable) {
            return $this->affectedResourcesForRental($input);
        }
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
                ['new_asset_id', 'new_asset_codigo'],
            ],
            'properties' => [
                'rental_id' => ['type' => 'integer'],
                'rental_codigo' => ['type' => 'string'],
                'new_asset_id' => ['type' => 'integer'],
                'new_asset_codigo' => ['type' => 'string'],
                'motivo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $newAsset = $this->resolveAsset([
            'asset_id' => $input['new_asset_id'] ?? null,
            'asset_codigo' => $input['new_asset_codigo'] ?? null,
        ]);

        $rental = $this->rentalService->substituteAsset(
            $rental,
            $newAsset,
            $input['motivo'] ?? null,
            $user,
        );

        return $this->success(
            "Patrimônio substituído na locação **{$rental->codigo}** — agora **{$rental->asset?->codigo_patrimonio}**.",
            $this->contextBuilder->rental($rental),
            [
                ['label' => 'Abrir locação', 'url' => route('rentals.show', $rental), 'primary' => true],
                ['label' => 'Ver patrimônio novo', 'url' => route('assets.show', $newAsset)],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $newAsset = $this->resolveAsset([
            'asset_id' => $input['new_asset_id'] ?? null,
            'asset_codigo' => $input['new_asset_codigo'] ?? null,
        ]);

        if (! in_array($rental->statusEnum(), [RentalStatus::Reservado, RentalStatus::Locado], true)) {
            return $this->failure('Substituição permitida apenas em reservas ou locações ativas.', 'business_rule');
        }

        if ($newAsset->id === $rental->asset_id) {
            return $this->failure('Selecione um patrimônio diferente do atual.', 'business_rule');
        }

        return AgentCommandResult::preview(
            "Simulação: substituir **{$rental->asset?->codigo_patrimonio}** por **{$newAsset->codigo_patrimonio}** na {$rental->codigo}.",
            [
                'rental_codigo' => $rental->codigo,
                'from_asset' => $rental->asset?->codigo_patrimonio,
                'to_asset' => $newAsset->codigo_patrimonio,
            ],
        );
    }
}
