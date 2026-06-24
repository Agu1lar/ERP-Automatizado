<?php

namespace Tests\Unit;

use App\Support\Agent\AgentLlmToolSchemaSanitizer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('agent')]
class AgentLlmToolSchemaSanitizerTest extends TestCase
{
    public function test_empty_properties_become_json_object(): void
    {
        $sanitized = (new AgentLlmToolSchemaSanitizer)->sanitize([
            'type' => 'object',
            'properties' => [],
        ]);

        $json = json_encode($sanitized);

        $this->assertSame('{"type":"object","properties":{}}', $json);
    }

    public function test_strips_non_standard_one_of_required(): void
    {
        $sanitized = (new AgentLlmToolSchemaSanitizer)->sanitize([
            'type' => 'object',
            'oneOfRequired' => [['rental_id', 'rental_codigo']],
            'properties' => [
                'rental_id' => ['type' => 'integer'],
            ],
        ]);

        $this->assertArrayNotHasKey('oneOfRequired', $sanitized);
        $this->assertArrayHasKey('rental_id', $sanitized['properties']);
    }

    public function test_strips_enum_values_for_groq_compatibility(): void
    {
        $sanitized = (new AgentLlmToolSchemaSanitizer)->sanitize([
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['locado', 'concluido']],
            ],
        ]);

        $this->assertArrayNotHasKey('enum', $sanitized['properties']['status']);
        $this->assertStringContainsString('locado', $sanitized['properties']['status']['description']);
    }
}
