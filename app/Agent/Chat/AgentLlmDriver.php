<?php

namespace App\Agent\Chat;

use App\Agent\AgentCommandRegistry;
use App\Enums\AgentCommandSurface;
use App\Enums\CopilotMode;
use App\Models\Domain\Agent\AgentLlmCall;
use App\Models\User;
use App\Services\AgentLlmUsageService;
use App\Support\Agent\AgentLlmConversationBuilder;
use App\Support\Agent\AgentLlmFailureClassifier;
use App\Support\Agent\AgentLlmHttp;
use App\Support\Agent\AgentLlmToolSchemaSanitizer;
use Illuminate\Support\Facades\Log;

class AgentLlmDriver
{
  public function __construct(
    private readonly AgentCommandRegistry $registry,
    private readonly AgentLlmUsageService $usageService,
    private readonly AgentLlmConversationBuilder $conversationBuilder,
    private readonly AgentLlmToolSchemaSanitizer $toolSchemaSanitizer,
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

  public function interpret(
    string $message,
    User $user,
    CopilotMode $mode = CopilotMode::Ask,
    ?int $sessionId = null,
  ): AgentLlmInterpretResult {
    if (! $this->isConfigured()) {
      return AgentLlmInterpretResult::skipped();
    }

    if (! filled(config('agent.llm.api_key'))) {
      return AgentLlmInterpretResult::failure(AgentLlmFailureClassifier::AUTH_ERROR);
    }

    if ($this->usageService->isQuotaExceeded($user)) {
      $this->usageService->record(
        callType: AgentLlmCall::TYPE_CHAT_INTERPRET,
        user: $user,
        success: false,
        failureReason: AgentLlmFailureClassifier::QUOTA_EXCEEDED,
        sessionId: $sessionId,
      );

      return AgentLlmInterpretResult::failure(AgentLlmFailureClassifier::QUOTA_EXCEEDED);
    }

    $started = microtime(true);

    try {
      $tools = collect($this->registry->manifest())
        ->when($mode === CopilotMode::Ask, fn ($c) => $c->filter(
          fn (array $entry) => ($entry['surface'] ?? '') === AgentCommandSurface::Visualization->value,
        ))
        ->map(fn (array $entry) => [
          'name' => $entry['name'],
          'description' => $entry['description'],
          'parameters' => $this->toolSchemaSanitizer->sanitize($entry['input_schema'] ?? []),
        ])
        ->values()
        ->all();

      $messages = $this->conversationBuilder->build($sessionId, $message, $mode);

      $response = AgentLlmHttp::client()
        ->post(rtrim((string) config('agent.llm.base_url'), '/').'/chat/completions', [
          'model' => config('agent.llm.model'),
          'messages' => $messages,
          'tools' => array_map(fn (array $tool) => [
            'type' => 'function',
            'function' => $tool,
          ], $tools),
          'tool_choice' => 'auto',
        ]);

      $latencyMs = (int) round((microtime(true) - $started) * 1000);

      if (! $response->successful()) {
        $reason = AgentLlmFailureClassifier::fromHttp($response->status(), $response->body());
        Log::warning('Agent LLM request failed', ['status' => $response->status(), 'reason' => $reason]);

        $this->usageService->record(
          callType: AgentLlmCall::TYPE_CHAT_INTERPRET,
          user: $user,
          success: false,
          failureReason: $reason,
          latencyMs: $latencyMs,
          sessionId: $sessionId,
        );

        return AgentLlmInterpretResult::failure($reason);
      }

      $usage = $response->json('usage');
      $usageArray = is_array($usage) ? $usage : null;

      $choice = $response->json('choices.0.message');

      if (! is_array($choice)) {
        $this->usageService->record(
          callType: AgentLlmCall::TYPE_CHAT_INTERPRET,
          user: $user,
          success: false,
          failureReason: AgentLlmFailureClassifier::UNKNOWN,
          usage: $usageArray,
          latencyMs: $latencyMs,
          sessionId: $sessionId,
        );

        return AgentLlmInterpretResult::failure(AgentLlmFailureClassifier::UNKNOWN);
      }

      if (! empty($choice['tool_calls'][0]['function'])) {
        $fn = $choice['tool_calls'][0]['function'];
        $input = json_decode($fn['arguments'] ?? '{}', true) ?: [];

        $this->usageService->record(
          callType: AgentLlmCall::TYPE_CHAT_INTERPRET,
          user: $user,
          success: true,
          usage: $usageArray,
          latencyMs: $latencyMs,
          sessionId: $sessionId,
        );

        return AgentLlmInterpretResult::success([
          'command' => $fn['name'] ?? null,
          'input' => $input,
          'reply' => $choice['content'] ?? null,
        ]);
      }

      if (! empty($choice['content'])) {
        $this->usageService->record(
          callType: AgentLlmCall::TYPE_CHAT_INTERPRET,
          user: $user,
          success: true,
          usage: $usageArray,
          latencyMs: $latencyMs,
          sessionId: $sessionId,
        );

        return AgentLlmInterpretResult::success(['reply' => (string) $choice['content']]);
      }

      $this->usageService->record(
        callType: AgentLlmCall::TYPE_CHAT_INTERPRET,
        user: $user,
        success: false,
        failureReason: AgentLlmFailureClassifier::UNKNOWN,
        usage: $usageArray,
        latencyMs: $latencyMs,
        sessionId: $sessionId,
      );

      return AgentLlmInterpretResult::failure(AgentLlmFailureClassifier::UNKNOWN);
    } catch (\Throwable $e) {
      $reason = AgentLlmFailureClassifier::fromException($e);
      $latencyMs = (int) round((microtime(true) - $started) * 1000);
      Log::warning('Agent LLM exception', ['error' => $e->getMessage(), 'reason' => $reason]);

      $this->usageService->record(
        callType: AgentLlmCall::TYPE_CHAT_INTERPRET,
        user: $user,
        success: false,
        failureReason: $reason,
        latencyMs: $latencyMs,
        sessionId: $sessionId,
      );

      return AgentLlmInterpretResult::failure($reason);
    }
  }
}
