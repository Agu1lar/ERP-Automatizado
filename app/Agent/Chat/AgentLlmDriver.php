<?php

namespace App\Agent\Chat;

use App\Agent\AgentCommandRegistry;
use App\Enums\AgentCommandSurface;
use App\Enums\CopilotMode;
use App\Models\User;
use App\Support\Agent\AgentLlmFailureClassifier;
use App\Support\AgentSystemPrompt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentLlmDriver
{
  public function __construct(
    private readonly AgentCommandRegistry $registry,
  ) {}

  public function isConfigured(): bool
  {
    return (bool) config('agent.llm.enabled');
  }

  public function isEnabled(): bool
  {
    return $this->isConfigured()
      && filled(config('agent.llm.api_key'));
  }

  public function interpret(string $message, User $user, CopilotMode $mode = CopilotMode::Ask): AgentLlmInterpretResult
  {
    if (! $this->isConfigured()) {
      return AgentLlmInterpretResult::skipped();
    }

    if (! filled(config('agent.llm.api_key'))) {
      return AgentLlmInterpretResult::failure(AgentLlmFailureClassifier::AUTH_ERROR);
    }

    try {
      $tools = collect($this->registry->manifest())
        ->when($mode === CopilotMode::Ask, fn ($c) => $c->filter(
          fn (array $entry) => ($entry['surface'] ?? '') === AgentCommandSurface::Visualization->value,
        ))
        ->map(fn (array $entry) => [
          'name' => $entry['name'],
          'description' => $entry['description'],
          'parameters' => $entry['input_schema'],
        ])
        ->values()
        ->all();

      $response = Http::withToken(config('agent.llm.api_key'))
        ->timeout((int) config('agent.llm.timeout', 30))
        ->post(rtrim((string) config('agent.llm.base_url'), '/').'/chat/completions', [
          'model' => config('agent.llm.model'),
          'messages' => [
            [
              'role' => 'system',
              'content' => AgentSystemPrompt::forLlm($mode),
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
        $reason = AgentLlmFailureClassifier::fromHttp($response->status(), $response->body());
        Log::warning('Agent LLM request failed', ['status' => $response->status(), 'reason' => $reason]);

        return AgentLlmInterpretResult::failure($reason);
      }

      $choice = $response->json('choices.0.message');

      if (! is_array($choice)) {
        return AgentLlmInterpretResult::failure(AgentLlmFailureClassifier::UNKNOWN);
      }

      if (! empty($choice['tool_calls'][0]['function'])) {
        $fn = $choice['tool_calls'][0]['function'];
        $input = json_decode($fn['arguments'] ?? '{}', true) ?: [];

        return AgentLlmInterpretResult::success([
          'command' => $fn['name'] ?? null,
          'input' => $input,
          'reply' => $choice['content'] ?? null,
        ]);
      }

      if (! empty($choice['content'])) {
        return AgentLlmInterpretResult::success(['reply' => (string) $choice['content']]);
      }

      return AgentLlmInterpretResult::failure(AgentLlmFailureClassifier::UNKNOWN);
    } catch (\Throwable $e) {
      $reason = AgentLlmFailureClassifier::fromException($e);
      Log::warning('Agent LLM exception', ['error' => $e->getMessage(), 'reason' => $reason]);

      return AgentLlmInterpretResult::failure($reason);
    }
  }
}
