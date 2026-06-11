<?php

namespace App\Enums;

enum AgentTaskStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Conflict = 'conflict';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Na fila',
            self::Running => 'Executando',
            self::Completed => 'Concluída',
            self::Failed => 'Falhou',
            self::Conflict => 'Conflito',
            self::Cancelled => 'Cancelada',
        };
    }
}
