<?php

namespace App\Livewire\Crm;

use App\Enums\OutboundChannel;
use App\Models\Domain\Crm\CommercialOpportunity;
use App\Services\CrmCampaignService;
use App\Services\OutboundMessagingService;
use App\Support\FlashMessage;
use App\Support\InactiveCustomerQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class InactiveCustomersIndex extends Component
{
    use AuthorizesRequests;

    #[Url]
    public int $months = 6;

    #[Url]
    public string $search = '';

    public string $channel = 'whatsapp';

    public string $custom_body = '';

    /** @var array<int> */
    public array $selected = [];

    public bool $selectAll = false;

    public function mount(): void
    {
        $this->authorize('viewAny', CommercialOpportunity::class);
        $this->months = max(1, min(24, $this->months ?: (int) config('crm.inactive_default_months', 6)));
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selected = $this->customers()->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $this->selected = [];
        }
    }

    public function queueCampaign(CrmCampaignService $campaigns, OutboundMessagingService $messaging): void
    {
        $this->authorize('create', CommercialOpportunity::class);

        if ($this->selected === []) {
            FlashMessage::error('Selecione ao menos um cliente.');

            return;
        }

        $channel = OutboundChannel::from($this->channel);
        $body = filled(trim($this->custom_body)) ? trim($this->custom_body) : null;

        $count = $campaigns->queueInactiveCampaign(
            $channel,
            $this->months,
            $this->selected,
            $body,
        );

        $this->selected = [];
        $this->selectAll = false;

        FlashMessage::success("{$count} mensagem(ns) enfileirada(s). Execute crm:process-outbound ou aguarde o agendador.");
    }

    public function previewBody(OutboundMessagingService $messaging): string
    {
        $customer = $this->customers()->first();

        if (! $customer) {
            return '';
        }

        return $messaging->renderTemplate(
            \App\Enums\OutboundMessageTemplate::InactiveCampaign,
            $customer,
            filled(trim($this->custom_body)) ? trim($this->custom_body) : null,
        );
    }

    public function render(CrmCampaignService $campaigns, InactiveCustomerQuery $inactiveQuery): View
    {
        $customers = $this->customers($campaigns);
        $preview = $customers->isNotEmpty()
            ? app(OutboundMessagingService::class)->renderTemplate(
                \App\Enums\OutboundMessageTemplate::InactiveCampaign,
                $customers->first(),
                filled(trim($this->custom_body)) ? trim($this->custom_body) : null,
            )
            : '';

        return view('livewire.crm.inactive-customers-index', [
            'customers' => $customers,
            'totalInactive' => $inactiveQuery->count($this->months),
            'canManage' => auth()->user()->can('crm.manage'),
            'preview' => $preview,
        ]);
    }

    private function customers(?CrmCampaignService $campaigns = null)
    {
        $campaigns ??= app(CrmCampaignService::class);

        return $campaigns->inactiveCustomers($this->months, $this->search);
    }
}
