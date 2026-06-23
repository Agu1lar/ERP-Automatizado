<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Models\User;

class KnowledgeGetCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'knowledge.get';
    }

    public static function description(): string
    {
        return 'Retorna a base de conhecimento operacional do ERP (fluxos, regras, documentos, limites do copiloto).';
    }

    public function permission(): string
    {
        return 'agent.api';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'section' => [
                    'type' => 'string',
                    'enum' => ['all', 'workflows', 'documents', 'operational_rules', 'domains'],
                    'description' => 'Opcional: filtrar seção. Padrão all.',
                ],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $payload = $this->contextBuilder->knowledge();
        $section = $input['section'] ?? 'all';

        if ($section !== 'all' && isset($payload[$section])) {
            $payload = [
                'version' => $payload['version'] ?? null,
                'section' => $section,
                $section => $payload[$section],
            ];
        }

        return $this->success(
            'Base de conhecimento operacional carregada. Use para orientar o usuário sobre fluxos, documentos e regras.',
            $payload,
        );
    }
}
