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
use App\Agent\Commands\BillingGetCommand;
use App\Agent\Commands\FinanceDelinquencyCommand;
use App\Agent\Commands\SearchGlobalCommand;
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
        FinanceSummaryCommand::class,
        FinanceAccountingExportCommand::class,
        FinanceDelinquencyCommand::class,
        SearchGlobalCommand::class,
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
    ],

    'access_permission' => 'agent.api',

    'operating_company_header' => 'X-Operating-Company-Id',

    'chat' => [
        'require_confirmation' => env('AGENT_CHAT_REQUIRE_CONFIRMATION', true),
        'max_attachments' => (int) env('AGENT_MAX_ATTACHMENTS', 3),
        'max_attachment_kb' => (int) env('AGENT_MAX_ATTACHMENT_KB', 10240),
    ],

    'llm' => [
        'enabled' => env('AGENT_LLM_ENABLED', false),
        'api_key' => env('AGENT_LLM_API_KEY'),
        'base_url' => env('AGENT_LLM_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('AGENT_LLM_MODEL', 'gpt-4o-mini'),
        'timeout' => env('AGENT_LLM_TIMEOUT', 30),
    ],

];
