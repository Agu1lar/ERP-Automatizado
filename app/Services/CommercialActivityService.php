<?php

namespace App\Services;

use App\Enums\CommercialActivityType;
use App\Models\Domain\Crm\CommercialActivity;
use App\Models\Domain\Crm\CommercialOpportunity;
use App\Models\Domain\Customer\Customer;
use App\Models\User;
use Carbon\CarbonInterface;

class CommercialActivityService
{
    public function log(
        Customer $customer,
        CommercialActivityType $type,
        string $descricao,
        ?CommercialOpportunity $opportunity = null,
        ?CarbonInterface $proximoFollowUp = null,
        ?User $user = null,
    ): CommercialActivity {
        $user ??= auth()->user();
        $now = now();

        $activity = CommercialActivity::create([
            'customer_id' => $customer->id,
            'commercial_opportunity_id' => $opportunity?->id,
            'tipo' => $type->value,
            'descricao' => trim($descricao),
            'user_id' => $user?->id,
            'proximo_follow_up_em' => $proximoFollowUp?->toDateString(),
        ]);

        $customerPayload = [
            'ultimo_contato_em' => $now,
        ];

        if ($proximoFollowUp) {
            $customerPayload['proximo_follow_up_em'] = $proximoFollowUp->toDateString();
        }

        $customer->update($customerPayload);

        if ($opportunity) {
            $oppPayload = ['ultimo_contato_em' => $now];

            if ($proximoFollowUp) {
                $oppPayload['proximo_follow_up_em'] = $proximoFollowUp->toDateString();
            }

            $opportunity->update($oppPayload);
        }

        return $activity;
    }
}
