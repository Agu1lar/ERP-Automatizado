<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\AssetStatus;
use App\Models\User;
use App\Services\AssetStatusService;

class AssetTransitionStatusCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AssetStatusService $statusService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'asset.transition_status';
    }

    public static function description(): string
    {
        return 'Altera o status operacional do patrimônio respeitando transições permitidas.';
    }

    public function permission(): string
    {
        return 'fleet.assets.change_status';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        try {
            $asset = $this->resolveAsset($input);

            return [['type' => 'asset', 'id' => $asset->id]];
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
            ],
            'required' => ['status'],
            'properties' => [
                'asset_id' => ['type' => 'integer'],
                'asset_codigo' => ['type' => 'string'],
                'status' => [
                    'type' => 'string',
                    'enum' => array_map(fn (AssetStatus $s) => $s->value, AssetStatus::cases()),
                ],
                'motivo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $asset = $this->resolveAsset($input);
        $newStatus = AssetStatus::from($input['status']);
        $current = AssetStatus::from($asset->status);

        if (! in_array($newStatus, $current->allowedTransitions(), true)) {
            return $this->failure(
                "Transição inválida: {$current->label()} → {$newStatus->label()}.",
                'business_rule',
            );
        }

        $asset = $this->statusService->transition(
            $asset,
            $newStatus,
            $input['motivo'] ?? null,
            $user,
        );

        return $this->success(
            "Patrimônio **{$asset->codigo_patrimonio}** agora está **{$newStatus->label()}**.",
            $this->contextBuilder->asset($asset),
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $asset = $this->resolveAsset($input);
        $newStatus = AssetStatus::from($input['status']);

        return AgentCommandResult::preview(
            "Simulação: alterar status de **{$asset->codigo_patrimonio}** para **{$newStatus->label()}**.",
            ['asset_codigo' => $asset->codigo_patrimonio, 'new_status' => $newStatus->value],
        );
    }
}
