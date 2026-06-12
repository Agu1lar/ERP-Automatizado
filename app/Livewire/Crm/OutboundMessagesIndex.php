<?php

namespace App\Livewire\Crm;

use App\Enums\OutboundMessageStatus;
use App\Models\Domain\Crm\CommercialOpportunity;
use App\Models\Domain\Crm\OutboundMessage;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class OutboundMessagesIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CommercialOpportunity::class);
    }

    public function render(): View
    {
        $query = OutboundMessage::query()
            ->with(['customer', 'createdBy'])
            ->latest();

        if (filled($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        return view('livewire.crm.outbound-messages-index', [
            'messages' => $query->paginate(25),
            'statusOptions' => OutboundMessageStatus::cases(),
            'buildWhatsAppLink' => fn (OutboundMessage $msg) => WhatsAppLinkBuilder::build($msg->recipient, $msg->body),
        ]);
    }
}
