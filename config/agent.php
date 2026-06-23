<?php

use App\Agent\Commands\AssetListCommand;
use App\Agent\Commands\AssetGetCommand;
use App\Agent\Commands\AssetMoveLocationCommand;
use App\Agent\Commands\AssetTransitionStatusCommand;
use App\Agent\Commands\BillingCreateRenewalCommand;
use App\Agent\Commands\CompanyGetCommand;
use App\Agent\Commands\CompanySearchCommand;
use App\Agent\Commands\PersonCreateCommand;
use App\Agent\Commands\PersonGetCommand;
use App\Agent\Commands\PersonSearchCommand;
use App\Agent\Commands\PersonUpdateCommand;
use App\Agent\Commands\CompanyCreateCommand;
use App\Agent\Commands\CompanyUpdateCommand;
use App\Agent\Commands\AdminSummaryCommand;
use App\Agent\Commands\AgentMetricsCommand;
use App\Agent\Commands\BillingGetCommand;
use App\Agent\Commands\CategoryListCommand;
use App\Agent\Commands\DocumentExportCommand;
use App\Agent\Commands\FleetAnalyticsCommand;
use App\Agent\Commands\FinanceDelinquencyCommand;
use App\Agent\Commands\ModelListCommand;
use App\Agent\Commands\PartGetCommand;
use App\Agent\Commands\PartListCommand;
use App\Agent\Commands\PreventiveDueCommand;
use App\Agent\Commands\PreventiveListCommand;
use App\Agent\Commands\PricingGetCommand;
use App\Agent\Commands\PricingListCommand;
use App\Agent\Commands\ReportCommercialCommand;
use App\Agent\Commands\ReportFinancialAnalysisCommand;
use App\Agent\Commands\SearchGlobalCommand;
use App\Agent\Commands\KnowledgeGetCommand;
use App\Agent\Commands\QuoteCancelCommand;
use App\Agent\Commands\QuoteCreateCommand;
use App\Agent\Commands\QuoteSendCommand;
use App\Agent\Commands\RentalTransferCommercialCommand;
use App\Agent\Commands\RentalUpdateCommand;
use App\Agent\Commands\BillingAuthorizeEntryCommand;
use App\Agent\Commands\BillingInvoiceEntryCommand;
use App\Agent\Commands\BillingListPendingCommand;
use App\Agent\Commands\BillingProcessCustomerPendingCommand;
use App\Agent\Commands\CustomerCreateCommand;
use App\Agent\Commands\CustomerUpdateCommand;
use App\Agent\Commands\DocumentApplyPlanCommand;
use App\Agent\Commands\CustomerGetCommand;
use App\Agent\Commands\CustomerSearchCommand;
use App\Agent\Commands\FinanceSummaryCommand;
use App\Agent\Commands\FinanceAccountingExportCommand;
use App\Agent\Commands\MaintenanceCompleteCommand;
use App\Agent\Commands\MaintenanceCompleteFieldCommand;
use App\Agent\Commands\MaintenanceGetCommand;
use App\Agent\Commands\MaintenanceListCommand;
use App\Agent\Commands\MaintenanceOpenCommand;
use App\Agent\Commands\MaintenanceResumeCommand;
use App\Agent\Commands\MaintenanceStartCommand;
use App\Agent\Commands\MaintenanceWaitPartCommand;
use App\Agent\Commands\QuoteConvertCommand;
use App\Agent\Commands\QuoteGetCommand;
use App\Agent\Commands\QuoteListCommand;
use App\Agent\Commands\ReceivableGetCommand;
use App\Agent\Commands\ReceivableListCommand;
use App\Agent\Commands\ReceivableMarkPaidCommand;
use App\Agent\Commands\YardListCommand;
use App\Agent\Commands\LogisticsDailyCommand;
use App\Agent\Commands\RentalCancelCommand;
use App\Agent\Commands\RentalCheckoutCommand;
use App\Agent\Commands\RentalCompleteInspectionCommand;
use App\Agent\Commands\RentalExtendCommand;
use App\Agent\Commands\RentalGetCommand;
use App\Agent\Commands\RentalListCommand;
use App\Agent\Commands\RentalReserveCommand;
use App\Agent\Commands\RentalReturnCommand;
use App\Agent\Commands\RentalSubstituteAssetCommand;
use App\Agent\Commands\RentalStatsCommand;

$agentLlmProvider = strtolower((string) env('AGENT_LLM_PROVIDER', 'openai'));

$agentLlmPresets = [
    'openai' => [
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o-mini',
        'timeout' => 30,
        'supports_vision' => true,
        'supports_json_mode' => true,
    ],
    'groq' => [
        'base_url' => 'https://api.groq.com/openai/v1',
        'model' => 'llama-3.3-70b-versatile',
        'timeout' => 60,
        'supports_vision' => false,
        'supports_json_mode' => true,
    ],
];

$agentLlmPreset = $agentLlmPresets[$agentLlmProvider] ?? $agentLlmPresets['openai'];

return [

    'commands' => [
        RentalGetCommand::class,
        RentalListCommand::class,
        RentalStatsCommand::class,
        AssetGetCommand::class,
        AssetListCommand::class,
        AssetMoveLocationCommand::class,
        AssetTransitionStatusCommand::class,
        RentalReserveCommand::class,
        RentalCheckoutCommand::class,
        RentalReturnCommand::class,
        RentalCancelCommand::class,
        RentalExtendCommand::class,
        RentalSubstituteAssetCommand::class,
        RentalCompleteInspectionCommand::class,
        CustomerSearchCommand::class,
        CustomerGetCommand::class,
        CustomerCreateCommand::class,
        CustomerUpdateCommand::class,
        BillingAuthorizeEntryCommand::class,
        BillingInvoiceEntryCommand::class,
        BillingListPendingCommand::class,
        BillingGetCommand::class,
        BillingProcessCustomerPendingCommand::class,
        ReceivableMarkPaidCommand::class,
        MaintenanceOpenCommand::class,
        MaintenanceGetCommand::class,
        MaintenanceListCommand::class,
        MaintenanceStartCommand::class,
        MaintenanceWaitPartCommand::class,
        MaintenanceResumeCommand::class,
        MaintenanceCompleteCommand::class,
        MaintenanceCompleteFieldCommand::class,
        FinanceSummaryCommand::class,
        FinanceAccountingExportCommand::class,
        FinanceDelinquencyCommand::class,
        SearchGlobalCommand::class,
        KnowledgeGetCommand::class,
        DocumentApplyPlanCommand::class,
        QuoteListCommand::class,
        QuoteGetCommand::class,
        QuoteCreateCommand::class,
        QuoteSendCommand::class,
        QuoteCancelCommand::class,
        QuoteConvertCommand::class,
        RentalUpdateCommand::class,
        RentalTransferCommercialCommand::class,
        BillingCreateRenewalCommand::class,
        PersonSearchCommand::class,
        PersonGetCommand::class,
        PersonCreateCommand::class,
        PersonUpdateCommand::class,
        CompanySearchCommand::class,
        CompanyGetCommand::class,
        CompanyCreateCommand::class,
        CompanyUpdateCommand::class,
        YardListCommand::class,
        LogisticsDailyCommand::class,
        ReceivableListCommand::class,
        ReceivableGetCommand::class,
        PricingListCommand::class,
        PricingGetCommand::class,
        CategoryListCommand::class,
        ModelListCommand::class,
        PartListCommand::class,
        PartGetCommand::class,
        PreventiveListCommand::class,
        PreventiveDueCommand::class,
        ReportCommercialCommand::class,
        ReportFinancialAnalysisCommand::class,
        FleetAnalyticsCommand::class,
        DocumentExportCommand::class,
        AdminSummaryCommand::class,
        AgentMetricsCommand::class,
    ],

    'access_permission' => 'agent.api',

    'operating_company_header' => 'X-Operating-Company-Id',

    'chat' => [
        'require_confirmation' => env('AGENT_CHAT_REQUIRE_CONFIRMATION', true),
        'max_attachments' => (int) env('AGENT_MAX_ATTACHMENTS', 3),
        'max_attachment_kb' => (int) env('AGENT_MAX_ATTACHMENT_KB', 10240),
        'max_history_messages' => (int) env('AGENT_CHAT_MAX_HISTORY', 20),
    ],

    'tasks' => [
        'sse_poll_ms' => (int) env('AGENT_TASK_SSE_POLL_MS', 1000),
        'sse_max_seconds' => (int) env('AGENT_TASK_SSE_MAX_SECONDS', 300),
        // Em local (ou AGENT_TASKS_INLINE=true) executa na hora — sem depender de queue:work.
        'run_inline_in_local' => filter_var(env('AGENT_TASKS_INLINE', false), FILTER_VALIDATE_BOOL)
            ?: env('APP_ENV', 'production') === 'local',
        'queued_stale_seconds' => (int) env('AGENT_TASK_QUEUED_STALE_SECONDS', 20),
    ],

    'llm' => [
        'provider' => $agentLlmProvider,
        'enabled' => env('AGENT_LLM_ENABLED', false),
        'api_key' => env('AGENT_LLM_API_KEY'),
        'base_url' => env('AGENT_LLM_BASE_URL', $agentLlmPreset['base_url']),
        'model' => env('AGENT_LLM_MODEL', $agentLlmPreset['model']),
        'timeout' => (int) env('AGENT_LLM_TIMEOUT', $agentLlmPreset['timeout']),
        'supports_vision' => filter_var(
            env('AGENT_LLM_SUPPORTS_VISION', $agentLlmPreset['supports_vision']),
            FILTER_VALIDATE_BOOL,
        ),
        'supports_json_mode' => filter_var(
            env('AGENT_LLM_SUPPORTS_JSON_MODE', $agentLlmPreset['supports_json_mode']),
            FILTER_VALIDATE_BOOL,
        ),
        'verify_ssl' => filter_var(env('AGENT_LLM_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
        'daily_token_limit' => env('AGENT_LLM_DAILY_TOKEN_LIMIT'),
        'pricing_per_million' => [
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'llama-3.3-70b-versatile' => ['input' => 0.59, 'output' => 0.79],
            'llama-3.1-70b-versatile' => ['input' => 0.59, 'output' => 0.79],
            'default' => ['input' => 0.15, 'output' => 0.60],
        ],
    ],

];
