<?php

namespace App\Console\Commands;

use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageTemplate;
use App\Models\Domain\Customer\Customer;
use App\Services\CommercialOpportunityService;
use App\Services\OutboundMessagingService;
use Illuminate\Console\Command;

class ProcessFollowUpReminders extends Command
{
    protected $signature = 'crm:follow-up-reminders';

    protected $description = 'Enfileira lembretes de follow-up comercial para o dia';

    public function handle(
        CommercialOpportunityService $opportunities,
        OutboundMessagingService $messaging,
    ): int {
        if (! config('crm.follow_up_reminder_enabled', true)) {
            $this->info('Lembretes de follow-up desabilitados.');

            return self::SUCCESS;
        }

        $queued = 0;
        $ref = 'follow-up-'.now()->format('Ymd');

        foreach ($opportunities->dueFollowUpsQuery()->get() as $opportunity) {
            $customer = $opportunity->customer;

            if (! $customer || ! filled($customer->telefone)) {
                continue;
            }

            $body = $messaging->renderTemplate(OutboundMessageTemplate::FollowUpReminder, $customer);

            $messaging->queue(
                $customer,
                OutboundChannel::Whatsapp,
                $body,
                OutboundMessageTemplate::FollowUpReminder,
                $ref,
            );

            $queued++;
        }

        $customers = Customer::query()
            ->whereDate('proximo_follow_up_em', '<=', now()->toDateString())
            ->whereNotNull('telefone')
            ->where('telefone', '!=', '')
            ->get();

        foreach ($customers as $customer) {
            $body = $messaging->renderTemplate(OutboundMessageTemplate::FollowUpReminder, $customer);

            $messaging->queue(
                $customer,
                OutboundChannel::Whatsapp,
                $body,
                OutboundMessageTemplate::FollowUpReminder,
                $ref.'-cust',
            );

            $queued++;
        }

        $this->info("Enfileirados {$queued} lembrete(s).");

        return self::SUCCESS;
    }
}
