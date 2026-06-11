<?php

namespace App\Http\Controllers\Api\Agent;

use App\Agent\AgentSessionService;
use App\Agent\Chat\AgentChatOptions;
use App\Agent\Chat\AgentChatOrchestrator;
use App\Enums\CopilotMode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
  public function __invoke(
    Request $request,
    AgentChatOrchestrator $orchestrator,
    AgentSessionService $sessionService,
  ): JsonResponse {
    $data = $request->validate([
      'message' => 'nullable|string|max:4000',
      'confirmed' => 'sometimes|boolean',
      'mode' => 'nullable|in:ask,agent',
      'command' => 'nullable|string',
      'input' => 'nullable|array',
      'session_id' => 'nullable|integer|exists:agent_sessions,id',
    ]);

    $mode = CopilotMode::tryFrom($data['mode'] ?? 'ask') ?? CopilotMode::Ask;

    $session = null;

    if (! empty($data['session_id'])) {
      $session = $sessionService->resolve($request->user(), 'api', (int) $data['session_id']);
    } else {
      $session = $sessionService->resolve($request->user(), 'api');
    }

    if (! empty($data['command']) && ($data['confirmed'] ?? false)) {
      $response = $orchestrator->executeConfirmed(
        $data['command'],
        $data['input'] ?? [],
        $request->user(),
        $session,
        $mode,
      );

      return response()->json(array_merge($response->toArray(), [
        'session_id' => $session->id,
        'mode' => $mode->value,
      ]));
    }

    $options = new AgentChatOptions(
      confirmed: (bool) ($data['confirmed'] ?? false),
      mode: $mode,
    );

    $response = $orchestrator->handle(
      (string) ($data['message'] ?? ''),
      $request->user(),
      $session,
      $options,
    );

    return response()->json(array_merge($response->toArray(), [
      'session_id' => $session->id,
      'mode' => $mode->value,
    ]));
  }
}
