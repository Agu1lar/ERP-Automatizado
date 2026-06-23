<?php

namespace App\Support\Agent;

/**
 * Normaliza input_schema dos comandos para APIs OpenAI-compatible (ex.: Groq).
 * A validação real continua no AgentCommandExecutor com o schema original do comando.
 */
class AgentLlmToolSchemaSanitizer
{
    /** @return array<string, mixed> */
    public function sanitize(array $schema): array
    {
        return $this->normalizeNode($schema);
    }

    /** @return array<string, mixed> */
    private function normalizeNode(array $schema): array
    {
        unset($schema['oneOfRequired'], $schema['oneOf']);

        if (array_key_exists('properties', $schema)) {
            $properties = $schema['properties'];

            if ($properties === [] || $properties === null) {
                $schema['properties'] = new \stdClass;
            } elseif (is_array($properties)) {
                $normalized = [];
                foreach ($properties as $key => $value) {
                    $normalized[$key] = is_array($value)
                        ? $this->normalizeNode($value)
                        : $value;
                }
                $schema['properties'] = $normalized;
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->normalizeNode($schema['items']);
        }

        foreach (['anyOf', 'allOf'] as $composite) {
            if (! isset($schema[$composite]) || ! is_array($schema[$composite])) {
                continue;
            }

            $schema[$composite] = array_map(
                fn (mixed $item) => is_array($item) ? $this->normalizeNode($item) : $item,
                $schema[$composite],
            );
        }

        return $schema;
    }
}
