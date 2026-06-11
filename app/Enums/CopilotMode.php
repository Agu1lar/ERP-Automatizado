<?php

namespace App\Enums;

enum CopilotMode: string
{
    case Ask = 'ask';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Ask => 'Pergunta',
            self::Agent => 'Agente',
        };
    }

    public function executesMutations(): bool
    {
        return $this === self::Agent;
    }
}
