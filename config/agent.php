<?php

use App\Agent\Commands\BillingAuthorizeEntryCommand;
use App\Agent\Commands\BillingInvoiceEntryCommand;
use App\Agent\Commands\BillingListPendingCommand;
use App\Agent\Commands\CustomerGetCommand;
use App\Agent\Commands\CustomerSearchCommand;
use App\Agent\Commands\FinanceSummaryCommand;
use App\Agent\Commands\MaintenanceCompleteCommand;
use App\Agent\Commands\MaintenanceOpenCommand;
use App\Agent\Commands\MaintenanceResumeCommand;
use App\Agent\Commands\MaintenanceStartCommand;
use App\Agent\Commands\MaintenanceWaitPartCommand;
use App\Agent\Commands\ReceivableMarkPaidCommand;
use App\Agent\Commands\RentalCheckoutCommand;
use App\Agent\Commands\RentalCompleteInspectionCommand;
use App\Agent\Commands\RentalGetCommand;
use App\Agent\Commands\RentalListCommand;
use App\Agent\Commands\RentalReserveCommand;
use App\Agent\Commands\RentalReturnCommand;

return [

    'commands' => [
        RentalGetCommand::class,
        RentalListCommand::class,
        RentalReserveCommand::class,
        RentalCheckoutCommand::class,
        RentalReturnCommand::class,
        RentalCompleteInspectionCommand::class,
        CustomerSearchCommand::class,
        CustomerGetCommand::class,
        BillingAuthorizeEntryCommand::class,
        BillingInvoiceEntryCommand::class,
        BillingListPendingCommand::class,
        ReceivableMarkPaidCommand::class,
        MaintenanceOpenCommand::class,
        MaintenanceStartCommand::class,
        MaintenanceWaitPartCommand::class,
        MaintenanceResumeCommand::class,
        MaintenanceCompleteCommand::class,
        FinanceSummaryCommand::class,
    ],

    'access_permission' => 'agent.api',

    'operating_company_header' => 'X-Operating-Company-Id',

    'chat' => [
        'require_confirmation' => env('AGENT_CHAT_REQUIRE_CONFIRMATION', true),
    ],

    'llm' => [
        'enabled' => env('AGENT_LLM_ENABLED', false),
        'api_key' => env('AGENT_LLM_API_KEY'),
        'base_url' => env('AGENT_LLM_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('AGENT_LLM_MODEL', 'gpt-4o-mini'),
        'timeout' => env('AGENT_LLM_TIMEOUT', 30),
    ],

];
