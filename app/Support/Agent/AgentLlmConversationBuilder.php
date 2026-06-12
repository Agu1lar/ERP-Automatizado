<?php

namespace App\Support\Agent;

use App\Enums\CopilotMode;
use App\Models\Domain\Agent\AgentMessage;
use App\Support\AgentSystemPrompt;

class AgentLlmConversationBuilder
{
    /** @return list<array{role: string, content: string}> */
    public function build(?int $sessionId, string $currentMessage, CopilotMode $mode): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => AgentSystemPrompt::forLlm($mode),
            ],
        ];

        $history = $this->historyMessages($sessionId);
        $messages = array_merge($messages, $history);

        $trimmedCurrent = trim($currentMessage);
        $last = $history !== [] ? $history[array_key_last($history)] : null;

        if ($trimmedCurrent !== '' && (
            $last === null
            || $last['role'] !== 'user'
            || trim($last['content']) !== $trimmedCurrent
        )) {
            $messages[] = ['role' => 'user', 'content' => $trimmedCurrent];
        }

        return $messages;
    }

    /** @return list<array{role: string, content: string}> */
    private function historyMessages(?int $sessionId): array
    {
        if (! $sessionId) {
            return [];
        }

        $limit = max(2, (int) config('agent.chat.max_history_messages', 20));
        $skip = ['(confirmação)', '(executar em background)'];

        return AgentMessage::query()
            ->where('agent_session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->filter(function (AgentMessage $message) use ($skip) {
                $content = trim($message->content);

                return $content !== '' && ! in_array($content, $skip, true);
            })
            ->map(fn (AgentMessage $message) => [
                'role' => $message->role,
                'content' => trim($message->content),
            ])
            ->values()
            ->all();
    }
}
