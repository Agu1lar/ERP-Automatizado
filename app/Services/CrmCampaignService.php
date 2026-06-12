<?php

namespace App\Services;

use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageTemplate;
use App\Models\Domain\Customer\Customer;
use App\Models\User;
use App\Support\InactiveCustomerQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CrmCampaignService
{
    public function __construct(
        private readonly InactiveCustomerQuery $inactiveQuery,
        private readonly OutboundMessagingService $messaging,
    ) {}

    /** @return Collection<int, Customer> */
    public function inactiveCustomers(int $monthsInactive = 6, ?string $search = null): Collection
    {
        $query = $this->inactiveQuery->baseQuery($monthsInactive)
            ->orderBy('nome');

        if (filled($search)) {
            $term = '%'.Str::lower(trim($search)).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(nome) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(telefone) LIKE ?', [$term]);
            });
        }

        return $query->limit(200)->get();
    }

    public function queueInactiveCampaign(
        OutboundChannel $channel,
        int $monthsInactive = 6,
        ?array $customerIds = null,
        ?string $customBody = null,
        ?User $user = null,
    ): int {
        $user ??= auth()->user();
        $campaignRef = 'inactive-'.now()->format('YmdHis');
        $customers = $customerIds
            ? Customer::query()->whereIn('id', $customerIds)->get()
            : $this->inactiveCustomers($monthsInactive);

        $queued = 0;

        foreach ($customers as $customer) {
            if (! filled($customer->telefone)) {
                continue;
            }

            $body = $this->messaging->renderTemplate(
                OutboundMessageTemplate::InactiveCampaign,
                $customer,
                $customBody,
            );

            $this->messaging->queue(
                $customer,
                $channel,
                $body,
                OutboundMessageTemplate::InactiveCampaign,
                $campaignRef,
                null,
                $user,
            );

            $queued++;
        }

        return $queued;
    }
}
