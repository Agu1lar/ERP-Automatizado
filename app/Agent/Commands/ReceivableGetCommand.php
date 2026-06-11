<?php

namespace App\Agent\Commands;

use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class ReceivableGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'receivable.get';
    }

    public static function description(): string
    {
        return 'Retorna o contexto completo de um título a receber.';
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['title_id', 'title_codigo'],
            ],
            'properties' => [
                'title_id' => ['type' => 'integer'],
                'title_codigo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $title = $this->resolveReceivableTitle($input);
        $context = $this->contextBuilder->receivableTitle($title);

        $overdueHint = $title->isOverdue()
            ? ' — **em atraso** ('.$title->daysOverdue().' dia(s))'
            : '';

        return $this->success(
            "Título **{$title->codigo}** — {$title->statusEnum()->label()}{$overdueHint}.",
            $context,
            [
                ['label' => 'Abrir títulos', 'url' => CopilotNavigationLinks::financeReceivables($title->codigo), 'primary' => true],
            ],
        );
    }
}
