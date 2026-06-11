<?php

namespace App\Support;

use App\Enums\CopilotMode;
use App\Support\Agent\AgentModeContext;

class AgentSystemPrompt
{
    public static function forLlm(?CopilotMode $mode = null): string
    {
        $mode ??= CopilotMode::Ask;

        return AgentModeContext::forLlm($mode);
    }
}
