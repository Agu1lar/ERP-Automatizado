<?php

namespace App\Enums;

enum OpportunityStage: string
{
    case Lead = 'lead';
    case Qualificacao = 'qualificacao';
    case Proposta = 'proposta';
    case Negociacao = 'negociacao';
    case Ganho = 'ganho';
    case Perdido = 'perdido';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Qualificacao => 'Qualificação',
            self::Proposta => 'Proposta',
            self::Negociacao => 'Negociação',
            self::Ganho => 'Ganho',
            self::Perdido => 'Perdido',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Ganho, self::Perdido], true);
    }

    /** @return list<self> */
    public static function pipelineStages(): array
    {
        return [
            self::Lead,
            self::Qualificacao,
            self::Proposta,
            self::Negociacao,
        ];
    }
}
