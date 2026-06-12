<?php

namespace App\Services;

use App\Models\Domain\Agent\AgentLlmCall;
use App\Models\User;
use App\Support\ActiveOperatingCompany;
use App\Support\Agent\AgentLlmFailureClassifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AgentLlmUsageService
{
    /**
     * @param  array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}|null  $usage
     */
    public function record(
        string $callType,
        User $user,
        bool $success,
        ?string $failureReason = null,
        ?array $usage = null,
        bool $usedFallback = false,
        ?int $latencyMs = null,
        ?int $sessionId = null,
        ?string $model = null,
    ): AgentLlmCall {
        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($prompt + $completion));
        $model ??= (string) config('agent.llm.model');

        return AgentLlmCall::create([
            'user_id' => $user->id,
            'operating_company_id' => ActiveOperatingCompany::id(),
            'agent_session_id' => $sessionId,
            'call_type' => $callType,
            'model' => $model,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'estimated_cost_usd' => $this->estimateCostUsd($model, $prompt, $completion),
            'success' => $success,
            'failure_reason' => $failureReason,
            'used_fallback' => $usedFallback,
            'latency_ms' => $latencyMs,
            'created_at' => now(),
        ]);
    }

    public function recordHeuristicFallback(User $user, ?string $llmFailureReason, ?int $sessionId = null): AgentLlmCall
    {
        return $this->record(
            callType: AgentLlmCall::TYPE_HEURISTIC_FALLBACK,
            user: $user,
            success: true,
            failureReason: $llmFailureReason,
            usedFallback: true,
            sessionId: $sessionId,
        );
    }

    /** @return array{allowed: bool, limit: int|null, used: int, remaining: int|null, scope: string|null} */
    public function quotaStatus(User $user): array
    {
        $limit = $this->resolveDailyTokenLimit($user);
        $used = $this->tokensUsedToday($user->id, ActiveOperatingCompany::id());

        if ($limit === null) {
            return [
                'allowed' => true,
                'limit' => null,
                'used' => $used,
                'remaining' => null,
                'scope' => null,
            ];
        }

        return [
            'allowed' => $used < $limit,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'scope' => $this->quotaScope($user),
        ];
    }

    public function isQuotaExceeded(User $user): bool
    {
        return ! $this->quotaStatus($user)['allowed'];
    }

    public function tokensUsedToday(?int $userId = null, ?int $operatingCompanyId = null): int
    {
        $query = AgentLlmCall::query()
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('call_type', [
                AgentLlmCall::TYPE_CHAT_INTERPRET,
                AgentLlmCall::TYPE_DOCUMENT_ANALYZE,
            ]);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($operatingCompanyId !== null) {
            $query->where('operating_company_id', $operatingCompanyId);
        }

        return (int) $query->sum('total_tokens');
    }

    public function costUsedToday(?int $userId = null, ?int $operatingCompanyId = null): float
    {
        $query = AgentLlmCall::query()
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('call_type', [
                AgentLlmCall::TYPE_CHAT_INTERPRET,
                AgentLlmCall::TYPE_DOCUMENT_ANALYZE,
            ]);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($operatingCompanyId !== null) {
            $query->where('operating_company_id', $operatingCompanyId);
        }

        return (float) $query->sum('estimated_cost_usd');
    }

    public function estimateCostUsd(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = config('agent.llm.pricing_per_million', []);
        $rates = $pricing[$model] ?? $pricing['default'] ?? ['input' => 0.15, 'output' => 0.60];

        $inputCost = ($promptTokens / 1_000_000) * (float) ($rates['input'] ?? 0);
        $outputCost = ($completionTokens / 1_000_000) * (float) ($rates['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    private function resolveDailyTokenLimit(User $user): ?int
    {
        if ($user->agent_daily_token_limit !== null) {
            return (int) $user->agent_daily_token_limit;
        }

        $company = ActiveOperatingCompany::current();

        if ($company?->agent_daily_token_limit !== null) {
            return (int) $company->agent_daily_token_limit;
        }

        $global = config('agent.llm.daily_token_limit');

        return $global !== null && $global !== '' ? (int) $global : null;
    }

    private function quotaScope(User $user): string
    {
        if ($user->agent_daily_token_limit !== null) {
            return 'user';
        }

        if (ActiveOperatingCompany::current()?->agent_daily_token_limit !== null) {
            return 'operating_company';
        }

        return 'global';
    }

    /** @return array{blocked: bool, reason: string} */
    public function localQuotaFailure(User $user): array
    {
        return [
            'blocked' => true,
            'reason' => AgentLlmFailureClassifier::QUOTA_EXCEEDED,
        ];
    }
}
