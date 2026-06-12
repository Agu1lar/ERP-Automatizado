<?php

namespace App\Services;

use App\Models\Domain\Agent\AgentCommandLog;
use App\Models\Domain\Agent\AgentLlmCall;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgentCopilotMetricsService
{
    public function __construct(
        private readonly AgentLlmUsageService $usageService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        ?int $operatingCompanyId = null,
        ?int $userId = null,
    ): array {
        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();

        $llm = $this->llmMetrics($from, $to, $operatingCompanyId, $userId);
        $commands = $this->commandMetrics($from, $to, $operatingCompanyId, $userId);
        $today = $this->todaySnapshot($operatingCompanyId, $userId);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'filters' => array_filter([
                'operating_company_id' => $operatingCompanyId,
                'user_id' => $userId,
            ]),
            'today' => $today,
            'llm' => $llm,
            'commands' => $commands,
        ];
    }

    /** @return array<string, mixed> */
    private function todaySnapshot(?int $operatingCompanyId, ?int $userId): array
    {
        $tokens = $this->usageService->tokensUsedToday($userId, $operatingCompanyId);
        $cost = $this->usageService->costUsedToday($userId, $operatingCompanyId);

        $llmQuery = AgentLlmCall::query()->whereDate('created_at', now()->toDateString());
        $this->applyScope($llmQuery, $operatingCompanyId, $userId, 'agent_llm_calls');

        $interpretAttempts = (clone $llmQuery)
            ->where('call_type', AgentLlmCall::TYPE_CHAT_INTERPRET)
            ->count();
        $fallbacks = (clone $llmQuery)->where('used_fallback', true)->count();

        $cmdQuery = AgentCommandLog::query()
            ->whereDate('created_at', now()->toDateString())
            ->where('dry_run', false);
        $this->applyScope($cmdQuery, $operatingCompanyId, $userId, 'agent_command_logs');

        return [
            'tokens' => $tokens,
            'estimated_cost_usd' => round($cost, 4),
            'llm_calls' => $interpretAttempts,
            'fallback_events' => $fallbacks,
            'commands_executed' => (clone $cmdQuery)->where('ok', true)->count(),
            'permission_denied' => (clone $cmdQuery)
                ->where('ok', false)
                ->where('result->error_code', 'forbidden')
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function llmMetrics(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $operatingCompanyId,
        ?int $userId,
    ): array {
        $base = AgentLlmCall::query()
            ->whereBetween('agent_llm_calls.created_at', [$from, $to]);
        $this->applyScope($base, $operatingCompanyId, $userId, 'agent_llm_calls');

        $interpret = (clone $base)->where('call_type', AgentLlmCall::TYPE_CHAT_INTERPRET);
        $attempts = (clone $interpret)->count();
        $successes = (clone $interpret)->where('success', true)->count();
        $failures = (clone $interpret)->where('success', false)->count();
        $fallbacks = (clone $base)->where('used_fallback', true)->count();
        $fallbackRows = (clone $base)->where('call_type', AgentLlmCall::TYPE_HEURISTIC_FALLBACK)->count();

        $tokens = (int) (clone $base)
            ->whereIn('call_type', [AgentLlmCall::TYPE_CHAT_INTERPRET, AgentLlmCall::TYPE_DOCUMENT_ANALYZE])
            ->sum('total_tokens');
        $cost = (float) (clone $base)
            ->whereIn('call_type', [AgentLlmCall::TYPE_CHAT_INTERPRET, AgentLlmCall::TYPE_DOCUMENT_ANALYZE])
            ->sum('estimated_cost_usd');

        $failureReasons = (clone $interpret)
            ->where('success', false)
            ->whereNotNull('failure_reason')
            ->select('failure_reason', DB::raw('count(*) as total'))
            ->groupBy('failure_reason')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['reason' => $row->failure_reason, 'count' => (int) $row->total])
            ->all();

        $byUser = $this->usageGroupedByUser($from, $to, $operatingCompanyId);
        $byCompany = $this->usageGroupedByCompany($from, $to, $userId);

        return [
            'interpret_attempts' => $attempts,
            'interpret_successes' => $successes,
            'interpret_failures' => $failures,
            'success_rate_percent' => $attempts > 0 ? round(($successes / $attempts) * 100, 1) : null,
            'fallback_events' => $fallbacks + $fallbackRows,
            'fallback_rate_percent' => $attempts > 0
                ? round((($fallbacks + $fallbackRows) / $attempts) * 100, 1)
                : null,
            'total_tokens' => $tokens,
            'estimated_cost_usd' => round($cost, 4),
            'failure_reasons' => $failureReasons,
            'usage_by_user' => $byUser,
            'usage_by_operating_company' => $byCompany,
        ];
    }

    /** @return array<string, mixed> */
    private function commandMetrics(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $operatingCompanyId,
        ?int $userId,
    ): array {
        $base = AgentCommandLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('dry_run', false);
        $this->applyScope($base, $operatingCompanyId, $userId, 'agent_command_logs');

        $topCommands = (clone $base)
            ->select('command', DB::raw('count(*) as total'))
            ->groupBy('command')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($row) => ['command' => $row->command, 'count' => (int) $row->total])
            ->all();

        $failuresByCode = (clone $base)
            ->where('ok', false)
            ->get(['result'])
            ->groupBy(fn (AgentCommandLog $log) => (string) (($log->result['error_code'] ?? null) ?: 'unknown'))
            ->map(fn (Collection $group, string $code) => ['error_code' => $code, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(15)
            ->values()
            ->all();

        $permissionDenied = collect($failuresByCode)
            ->firstWhere('error_code', 'forbidden')['count'] ?? 0;

        return [
            'executed' => (clone $base)->where('ok', true)->count(),
            'failed' => (clone $base)->where('ok', false)->count(),
            'permission_denied' => (int) $permissionDenied,
            'top_commands' => $topCommands,
            'failures_by_error_code' => $failuresByCode,
        ];
    }

    /** @return list<array{user_id: int, user_name: string, tokens: int, cost_usd: float, calls: int}> */
    private function usageGroupedByUser(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $operatingCompanyId,
    ): array {
        $query = AgentLlmCall::query()
            ->whereBetween('agent_llm_calls.created_at', [$from, $to])
            ->whereIn('agent_llm_calls.call_type', [AgentLlmCall::TYPE_CHAT_INTERPRET, AgentLlmCall::TYPE_DOCUMENT_ANALYZE])
            ->join('users', 'users.id', '=', 'agent_llm_calls.user_id')
            ->select(
                'agent_llm_calls.user_id',
                'users.name as user_name',
                DB::raw('SUM(agent_llm_calls.total_tokens) as tokens'),
                DB::raw('SUM(agent_llm_calls.estimated_cost_usd) as cost_usd'),
                DB::raw('COUNT(*) as calls'),
            )
            ->groupBy('agent_llm_calls.user_id', 'users.name')
            ->orderByDesc('tokens')
            ->limit(10);

        if ($operatingCompanyId !== null) {
            $query->where('agent_llm_calls.operating_company_id', $operatingCompanyId);
        }

        return $query->get()->map(fn ($row) => [
            'user_id' => (int) $row->user_id,
            'user_name' => (string) $row->user_name,
            'tokens' => (int) $row->tokens,
            'cost_usd' => round((float) $row->cost_usd, 4),
            'calls' => (int) $row->calls,
        ])->all();
    }

    /** @return list<array{operating_company_id: int|null, company_name: string, tokens: int, cost_usd: float, calls: int}> */
    private function usageGroupedByCompany(CarbonInterface $from, CarbonInterface $to, ?int $userId): array
    {
        $query = AgentLlmCall::query()
            ->whereBetween('agent_llm_calls.created_at', [$from, $to])
            ->whereIn('agent_llm_calls.call_type', [AgentLlmCall::TYPE_CHAT_INTERPRET, AgentLlmCall::TYPE_DOCUMENT_ANALYZE])
            ->leftJoin('operating_companies', 'operating_companies.id', '=', 'agent_llm_calls.operating_company_id')
            ->select(
                'agent_llm_calls.operating_company_id',
                DB::raw("COALESCE(operating_companies.nome, '—') as company_name"),
                DB::raw('SUM(agent_llm_calls.total_tokens) as tokens'),
                DB::raw('SUM(agent_llm_calls.estimated_cost_usd) as cost_usd'),
                DB::raw('COUNT(*) as calls'),
            )
            ->groupBy('agent_llm_calls.operating_company_id', 'operating_companies.nome')
            ->orderByDesc('tokens')
            ->limit(10);

        if ($userId !== null) {
            $query->where('agent_llm_calls.user_id', $userId);
        }

        return $query->get()->map(fn ($row) => [
            'operating_company_id' => $row->operating_company_id !== null ? (int) $row->operating_company_id : null,
            'company_name' => (string) $row->company_name,
            'tokens' => (int) $row->tokens,
            'cost_usd' => round((float) $row->cost_usd, 4),
            'calls' => (int) $row->calls,
        ])->all();
    }

  /** @param  \Illuminate\Database\Eloquent\Builder<AgentLlmCall>| \Illuminate\Database\Eloquent\Builder<AgentCommandLog>  $query */
    private function applyScope($query, ?int $operatingCompanyId, ?int $userId, string $table): void
    {
        if ($operatingCompanyId !== null) {
            $query->where("{$table}.operating_company_id", $operatingCompanyId);
        }

        if ($userId !== null) {
            $query->where("{$table}.user_id", $userId);
        }
    }
}
