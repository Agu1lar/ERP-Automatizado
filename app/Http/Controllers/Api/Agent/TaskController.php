<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Domain\Agent\AgentTask;
use App\Services\AgentTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = AgentTask::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(min((int) $request->query('limit', 20), 50))
            ->get()
            ->map(fn (AgentTask $task) => $task->toAgentArray());

        return response()->json(['tasks' => $tasks]);
    }

    public function show(AgentTask $task): JsonResponse
    {
        abort_unless($task->user_id === auth()->id(), 403);

        return response()->json($task->toAgentArray());
    }

    public function store(Request $request, AgentTaskService $taskService): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'steps' => 'required|array|min:1',
            'steps.*.command' => 'required|string',
            'steps.*.params' => 'nullable|array',
            'steps.*.label' => 'nullable|string',
            'session_id' => 'nullable|integer|exists:agent_sessions,id',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $session = null;

        if (! empty($data['session_id'])) {
            $session = app(\App\Agent\AgentSessionService::class)
                ->resolve($request->user(), 'api', (int) $data['session_id']);
        }

        $task = $taskService->queue(
            $request->user(),
            $data['steps'],
            $data['title'],
            $session,
            $data['idempotency_key'] ?? null,
        );

        return response()->json($task->toAgentArray(), 202);
    }

    public function cancel(AgentTask $task, AgentTaskService $taskService): JsonResponse
    {
        abort_unless($task->user_id === auth()->id(), 403);

        return response()->json($taskService->cancel($task)->toAgentArray());
    }
}
