<?php

namespace App\Http\Controllers\Api\Agent;

use App\Enums\AgentTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Domain\Agent\AgentTask;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskStreamController extends Controller
{
    public function __invoke(AgentTask $task, Request $request): StreamedResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);

        $terminal = [
            AgentTaskStatus::Completed->value,
            AgentTaskStatus::Failed->value,
            AgentTaskStatus::Conflict->value,
            AgentTaskStatus::Cancelled->value,
        ];

        $pollMs = max(250, (int) config('agent.tasks.sse_poll_ms', 1000));
        $maxSeconds = max(30, (int) config('agent.tasks.sse_max_seconds', 300));

        return response()->stream(function () use ($task, $terminal, $pollMs, $maxSeconds) {
            $started = time();
            $lastPayload = null;

            while ((time() - $started) < $maxSeconds) {
                $task->refresh();
                $payload = $task->toAgentArray();
                $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);

                if ($encoded !== $lastPayload) {
                    echo "event: task\n";
                    echo 'data: '.$encoded."\n\n";
                    $lastPayload = $encoded;
                } else {
                    echo ": keepalive\n\n";
                }

                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                if (in_array($task->status, $terminal, true)) {
                    echo "event: close\n";
                    echo 'data: '.$encoded."\n\n";
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                    break;
                }

                usleep($pollMs * 1000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
