<?php

namespace App\Enums;

/**
 * Superfície da API perante o copiloto.
 *
 * - Visualization: consultar, filtrar, abrir telas, exportar — não altera cadastros.
 * - Execution: mutações reais (campos, fluxos, transições de status).
 */
enum AgentCommandSurface: string
{
    case Visualization = 'visualization';
    case Execution = 'execution';

    public function label(): string
    {
        return match ($this) {
            self::Visualization => 'Visualização / consulta',
            self::Execution => 'Execução / mutação',
        };
    }

    public function copilotMode(): CopilotMode
    {
        return match ($this) {
            self::Visualization => CopilotMode::Ask,
            self::Execution => CopilotMode::Agent,
        };
    }
}
