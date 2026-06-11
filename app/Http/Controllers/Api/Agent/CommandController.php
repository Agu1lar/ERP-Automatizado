<?php

namespace App\Http\Controllers\Api\Agent;

use App\Agent\AgentCommandExecutor;
use App\Agent\AgentCommandRegistry;
use App\Agent\AgentSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandController extends Controller
{
  public function execute(
    Request $request,
    string $command,
    AgentCommandRegistry $registry,
    AgentCommandExecutor $executor,
    AgentSessionService $sessionService,
  ): JsonResponse {
    if (! $registry->has($command)) {
      return response()->json([
        'ok' => false,
        'message' => "Comando desconhecido: {$command}",
        'error_code' => 'unknown_command',
      ], 404);
    }

    $validated = $request->validate([
      'input' => 'nullable|array',
      'dry_run' => 'sometimes|boolean',
      'session_id' => 'nullable|integer|exists:agent_sessions,id',
    ]);

    /** @var array<string, mixed> $input */
    $input = $validated['input'] ?? [];
    $dryRun = (bool) ($validated['dry_run'] ?? false);

    $session = null;

    if (! empty($validated['session_id'])) {
      $session = $sessionService->resolve($request->user(), 'api', (int) $validated['session_id']);
    }

    $result = $executor->execute($command, $input, $request->user(), $session, $dryRun);

    $status = match ($result->errorCode) {
      'forbidden' => 403,
      'unknown_command' => 404,
      'validation_failed', 'business_rule', 'dry_run_unsupported' => 422,
      default => $result->ok ? 200 : 500,
    };

    return response()->json($result->toArray(), $status);
  }
}
