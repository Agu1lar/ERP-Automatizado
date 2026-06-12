<?php

namespace App\Models\Domain\Crm;

use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Enums\OutboundMessageTemplate;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Customer\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundMessage extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'customer_id',
        'channel',
        'template',
        'recipient',
        'body',
        'status',
        'provider_response',
        'campaign_ref',
        'scheduled_at',
        'sent_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function channelEnum(): OutboundChannel
    {
        return OutboundChannel::from($this->channel);
    }

    public function statusEnum(): OutboundMessageStatus
    {
        return OutboundMessageStatus::from($this->status);
    }

    public function templateEnum(): ?OutboundMessageTemplate
    {
        return $this->template ? OutboundMessageTemplate::from($this->template) : null;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
