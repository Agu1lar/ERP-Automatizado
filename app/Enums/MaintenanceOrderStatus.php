<?php

namespace App\Enums;

enum MaintenanceOrderStatus: string
{
    case Aberta = 'aberta';
    case EmExecucao = 'em_execucao';
    case AguardandoPeca = 'aguardando_peca';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::EmExecucao => 'Em execução',
            self::AguardandoPeca => 'Aguardando peça',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Aberta => 'blue',
            self::EmExecucao => 'orange',
            self::AguardandoPeca => 'amber',
            self::Concluida => 'green',
            self::Cancelada => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Aberta, self::EmExecucao, self::AguardandoPeca], true);
    }
}
