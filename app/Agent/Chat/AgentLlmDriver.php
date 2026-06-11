<?php

namespace App\Agent\Chat;

use App\Agent\AgentCommandRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentLlmDriver
{
  public function __construct(
    private readonly AgentCommandRegistry $registry,
  ) {}

  public function isEnabled(): bool
  {
    return (bool) config('agent.llm.enabled')
      && filled(config('agent.llm.api_key'));
  }

  /**
   * @return array{command?: string, input?: array<string, mixed>, reply?: string}|null
   */
  public function interpret(string $message, User $user): ?array
  {
    if (! $this->isEnabled()) {
      return null;
    }

    try {
      $tools = collect($this->registry->manifest())
        ->map(fn (array $entry) => [
          'name' => $entry['name'],
          'description' => $entry['description'],
          'parameters' => $entry['input_schema'],
        ])
        ->values()
        ->all();

      $response = Http::withToken(config('agent.llm.api_key'))
        ->timeout((int) config('agent.llm.timeout', 30))
        ->post(rtrim(config('agent.llm.base_url'), '/').'/chat/completions', [
          'model' => config('agent.llm.model'),
          'messages' => [
            [
              'role' => 'system',
              'content' => 'Você é o copiloto do ERP Gestão Acesso (locação de equipamentos). Escolha no máximo um tool/comando para executar. Se faltar dado, responda em português pedindo clarificação sem inventar IDs.',
            ],
            ['role' => 'user', 'content' => $message],
          ],
          'tools' => array_map(fn (array $tool) => [
            'type' => 'function',
            'function' => $tool,
          ], $tools),
          'tool_choice' => 'auto',
        ]);

      if (! $response->successful()) {
        Log::warning('Agent LLM request failed', ['status' => $response->status(), 'body' => $response->body()]);

        return null;
      }

      $choice = $response->json('choices.0.message');

      if (! is_array($choice)) {
        return null;
      }

      if (! empty($choice['tool_calls'][0]['function'])) {
        $fn = $choice['tool_calls'][0]['function'];
        $input = json_decode($fn['arguments'] ?? '{}', true) ?: [];

        return [
          'command' => $fn['name'] ?? null,
          'input' => $input,
          'reply' => $choice['content'] ?? "Executar {$fn['name']}.",
        ];
      }

      if (! empty($choice['content'])) {
        return ['reply' => (string) $choice['content']];
      }
    } catch (\Throwable $e) {
      Log::warning('Agent LLM exception', ['error' => $e->getMessage()]);
    }

    return null;
  }
}
