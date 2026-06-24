<?php

namespace Tests\Unit;

use App\Agent\Chat\AgentHeuristicParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('agent')]
class AgentHeuristicParserTest extends TestCase
{
    public function test_parses_recent_open_maintenance_orders(): void
    {
        $parsed = (new AgentHeuristicParser)->parse('mostrar as 3 ultimas OS abertas');

        $this->assertSame('maintenance.list', $parsed['command'] ?? null);
        $this->assertSame(3, $parsed['input']['limit'] ?? null);
        $this->assertTrue($parsed['input']['open_only'] ?? false);
    }
}
