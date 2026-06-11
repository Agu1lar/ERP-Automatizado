<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\User;
use App\Services\AssetMovementService;

class AssetMoveLocationCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AssetMovementService $movementService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'asset.move_location';
    }

    public static function description(): string
    {
        return 'Registra movimentação de localização física do patrimônio (pátio, obra, etc.).';
    }

    public function permission(): string
    {
        return 'records.edit';
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
            'required' => ['destino'],
            'properties' => [
                'asset_id' => ['type' => 'integer'],
                'asset_codigo' => ['type' => 'string'],
                'destino' => ['type' => 'string'],
                'motivo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $asset = $this->resolveAsset($input);
        $destino = trim((string) $input['destino']);

        if ($destino === '') {
            return $this->failure('Informe o destino da movimentação.', 'validation_failed');
        }

        $origem = $asset->localizacao;
        $asset = $this->movementService->moveLocation(
            $asset,
            $destino,
            $input['motivo'] ?? null,
            $user,
        );

        return $this->success(
            "Patrimônio **{$asset->codigo_patrimonio}** movido de \"{$origem}\" para \"{$destino}\".",
            $this->contextBuilder->asset($asset),
            [['label' => 'Abrir patrimônio', 'url' => route('assets.show', $asset), 'primary' => true]],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $asset = $this->resolveAsset($input);
        $destino = trim((string) ($input['destino'] ?? ''));

        return AgentCommandResult::preview(
            "Simulação: mover **{$asset->codigo_patrimonio}** ({$asset->localizacao}) → **{$destino}**.",
            ['asset_codigo' => $asset->codigo_patrimonio, 'destino' => $destino],
        );
    }
}
