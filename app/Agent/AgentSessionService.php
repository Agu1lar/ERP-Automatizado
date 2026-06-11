<?php

namespace App\Agent;

use App\Models\Domain\Agent\AgentCommandLog;
use App\Models\Domain\Agent\AgentMessage;
use App\Models\Domain\Agent\AgentSession;
use App\Models\User;
use App\Support\ActiveOperatingCompany;
use Illuminate\Support\Facades\Request;

class AgentSessionService
{
    public function resolve(User $user, string $channel = 'web', ?int $sessionId = null): AgentSession
    {
        if ($sessionId) {
            $session = AgentSession::query()
                ->whereKey($sessionId)
                ->where('user_id', $user->id)
                ->first();

            if ($session) {
                $session->touchActivity();

                return $session;
            }
        }

        return AgentSession::create([
            'user_id' => $user->id,
            'operating_company_id' => ActiveOperatingCompany::id(),
            'channel' => $channel,
            'last_activity_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $input */
    public function setPendingExecution(AgentSession $session, string $command, array $input): void
    {
        $session->update([
            'pending_execution' => [
                'command' => $command,
                'input' => $input,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /** @return array{command: string, input: array<string, mixed>}|null */
    public function getPendingExecution(AgentSession $session): ?array
    {
        $pending = $session->fresh()->pending_execution;

        if (! is_array($pending) || empty($pending['command'])) {
            return null;
        }

        return [
            'command' => (string) $pending['command'],
            'input' => is_array($pending['input'] ?? null) ? $pending['input'] : [],
        ];
    }

    public function clearPendingExecution(AgentSession $session): void
    {
        if ($session->pending_execution !== null) {
            $session->update(['pending_execution' => null]);
        }
    }

    /** @param  array<string, mixed>|null  $meta */
    public function logMessage(AgentSession $session, string $role, string $content, ?array $meta = null): AgentMessage
    {
        $session->touchActivity();

        return AgentMessage::create([
            'agent_session_id' => $session->id,
            'role' => $role,
            'content' => $content,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $input */
    public function logCommand(
        ?AgentSession $session,
        User $user,
        string $command,
        array $input,
        AgentCommandResult $result,
        bool $dryRun = false,
    ): AgentCommandLog {
        if ($session) {
            $session->touchActivity();
        }

        return AgentCommandLog::create([
            'agent_session_id' => $session?->id,
            'user_id' => $user->id,
            'operating_company_id' => ActiveOperatingCompany::id(),
            'command' => $command,
            'input' => $input,
            'result' => $result->toArray(),
            'dry_run' => $dryRun,
            'ok' => $result->ok,
            'ip' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
