<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class PartGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'part.get';
    }

    public static function description(): string
    {
        return 'Retorna detalhes de uma peça do catálogo (código ou id).';
    }

    public function permission(): string
    {
        return 'maintenance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['part_id', 'part_codigo'],
            ],
            'properties' => [
                'part_id' => ['type' => 'integer'],
                'part_codigo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $part = $this->resolvePart($input);
        $context = $this->contextBuilder->partCatalogItem($part);

        return $this->success(
            "Peça **{$part->codigo_peca}** — {$part->descricao}.",
            $context,
            [
                ['label' => 'Abrir catálogo', 'url' => CopilotNavigationLinks::partsCatalog($part->codigo_peca), 'primary' => true],
            ],
        );
    }
}
