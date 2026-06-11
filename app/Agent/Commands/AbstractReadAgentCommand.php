<?php

namespace App\Agent\Commands;

use App\Enums\AgentCommandKind;
use App\Enums\AgentCommandSurface;

abstract class AbstractReadAgentCommand extends AbstractAgentCommand
{
    public function commandKind(): AgentCommandKind
    {
        return AgentCommandKind::Read;
    }

    public function commandSurface(): AgentCommandSurface
    {
        return AgentCommandSurface::Visualization;
    }
}
