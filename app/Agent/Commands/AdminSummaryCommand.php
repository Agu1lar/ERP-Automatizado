<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\Domain\Agent\AgentCommandLog;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;
use App\Services\AgentLlmUsageService;
use App\Support\CopilotNavigationLinks;

class AdminSummaryCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'admin.summary';
    }

    public static function description(): string
    {
        return 'Visão administrativa: usuários, empresas operacionais, auditoria e logs do copiloto (somente leitura).';
    }

    public function permission(): string
    {
        return 'audit.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $canUsers = $user->can('admin.users.view');
        $canOperatingCompanies = $user->can('admin.companies.manage');
        $canAudit = $user->can('audit.view');

        $activeUsers = $canUsers
            ? User::query()->where('ativo', true)->count()
            : null;
        $operatingCompanies = $canOperatingCompanies
            ? OperatingCompany::query()->where('ativo', true)->count()
            : null;
        $recentAgentLogs = $canAudit
            ? AgentCommandLog::query()->where('created_at', '>=', now()->subDay())->count()
            : null;

        $sections = [];

        if ($canUsers) {
            $sections[] = "• Usuários ativos: **{$activeUsers}**";
        }

        if ($canOperatingCompanies) {
            $sections[] = "• Empresas operacionais ativas: **{$operatingCompanies}**";
        }

        if ($canAudit) {
            $usage = app(AgentLlmUsageService::class);
            $tokensToday = $usage->tokensUsedToday();
            $costToday = $usage->costUsedToday();
            $sections[] = "• Comandos do copiloto (24h): **{$recentAgentLogs}**";
            $sections[] = '• LLM hoje: **'.number_format($tokensToday, 0, ',', '.').'** tokens (~US$ '.number_format($costToday, 4, ',', '.').')';
        }

        $message = "**Administração**\n\n"
            .($sections !== [] ? implode("\n", $sections)."\n\n" : '')
            .'Use os atalhos para abrir usuários, empresas operacionais ou auditoria.';

        $nextSteps = [];

        if ($canUsers) {
            $nextSteps[] = ['label' => 'Usuários', 'url' => CopilotNavigationLinks::adminUsers(), 'primary' => true];
        }

        if ($canOperatingCompanies) {
            $nextSteps[] = ['label' => 'Empresas operacionais', 'url' => CopilotNavigationLinks::adminOperatingCompanies()];
        }

        if ($canAudit) {
            $nextSteps[] = ['label' => 'Métricas do copiloto', 'url' => CopilotNavigationLinks::adminAgentMetrics(), 'primary' => true];
            $nextSteps[] = ['label' => 'Auditoria', 'url' => CopilotNavigationLinks::adminAudit()];
            $nextSteps[] = ['label' => 'Logs do copiloto', 'url' => CopilotNavigationLinks::adminAgentLogs()];
        }

        return $this->success(
            $message,
            [
                'entity' => 'admin_summary',
                'permissions' => [
                    'users' => $canUsers,
                    'operating_companies' => $canOperatingCompanies,
                    'audit' => $canAudit,
                ],
                'counts' => array_filter([
                    'active_users' => $activeUsers,
                    'operating_companies' => $operatingCompanies,
                    'agent_logs_24h' => $recentAgentLogs,
                ], fn ($v) => $v !== null),
            ],
            $nextSteps,
        );
    }
}
