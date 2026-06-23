<?php

namespace Tests\Unit;

use App\Support\Agent\AgentModeContext;
use App\Support\Agent\AgentSystemKnowledge;
use App\Support\AgentSystemPrompt;
use App\Enums\CopilotMode;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('agent')]
class AgentSystemKnowledgePromptTest extends TestCase
{
    public function test_compact_for_llm_includes_key_workflows(): void
    {
        $compact = AgentSystemKnowledge::compactForLlm();

        $this->assertStringContainsString('LOC', $compact);
        $this->assertStringContainsString('maintenance.complete_field', $compact);
        $this->assertStringContainsString('knowledge.get', $compact);
    }

    public function test_llm_system_prompt_includes_compact_knowledge(): void
    {
        $prompt = AgentSystemPrompt::forLlm(CopilotMode::Agent);

        $this->assertStringContainsString(AgentSystemKnowledge::compactForLlm(), $prompt);
        $this->assertStringContainsString('MODO AGENTE', AgentModeContext::forLlm(CopilotMode::Agent));
    }
}
