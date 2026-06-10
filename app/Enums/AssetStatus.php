<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Disponivel = 'disponivel';
    case Reservado = 'reservado';
    case Locado = 'locado';
    case EmInspecao = 'em_inspecao';
    case EmManutencaoCampo = 'em_manutencao_campo';
    case Extraviado = 'extraviado';
    case EmManutencao = 'em_manutencao';
    case AguardandoPeca = 'aguardando_peca';
    case Bloqueado = 'bloqueado';
    case Sucata = 'sucata';
    case Cancelado = 'cancelado';
    case Arquivado = 'arquivado';

    public function label(): string
    {
        return match ($this) {
            self::Disponivel => 'Disponível',
            self::Reservado => 'Reservado',
            self::Locado => 'Locado',
            self::EmInspecao => 'Em inspeção',
            self::EmManutencaoCampo => 'Em manutenção em campo',
            self::Extraviado => 'Extraviado',
            self::EmManutencao => 'Em manutenção',
            self::AguardandoPeca => 'Aguardando peça',
            self::Bloqueado => 'Bloqueado',
            self::Sucata => 'Sucata/Vendido',
            self::Cancelado => 'Cancelado',
            self::Arquivado => 'Arquivado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Disponivel => 'green',
            self::Reservado => 'blue',
            self::Locado => 'indigo',
            self::EmInspecao => 'yellow',
            self::EmManutencaoCampo, self::EmManutencao => 'orange',
            self::Extraviado => 'red',
            self::AguardandoPeca => 'amber',
            self::Bloqueado => 'gray',
            self::Sucata, self::Arquivado => 'slate',
            self::Cancelado => 'zinc',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Sucata, self::Arquivado], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Disponivel => [
                self::Reservado,
                self::Locado,
                self::EmManutencao,
                self::Bloqueado,
                self::Sucata,
            ],
            self::Reservado => [
                self::Locado,
                self::Disponivel,
                self::Cancelado,
            ],
            self::Locado => [
                self::EmInspecao,
                self::EmManutencaoCampo,
                self::Extraviado,
            ],
            self::EmInspecao => [
                self::Disponivel,
                self::EmManutencao,
                self::Bloqueado,
            ],
            self::EmManutencao => [
                self::AguardandoPeca,
                self::Disponivel,
                self::Sucata,
                self::Bloqueado,
            ],
            self::EmManutencaoCampo => [
                self::EmInspecao,
                self::EmManutencao,
                self::Extraviado,
            ],
            self::AguardandoPeca => [
                self::EmManutencao,
                self::Disponivel,
                self::Sucata,
            ],
            self::Bloqueado => [
                self::Disponivel,
                self::EmManutencao,
                self::Sucata,
            ],
            self::Sucata => [self::Arquivado],
            self::Extraviado => [self::EmInspecao, self::Bloqueado],
            self::Cancelado => [self::Disponivel],
            self::Arquivado => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
