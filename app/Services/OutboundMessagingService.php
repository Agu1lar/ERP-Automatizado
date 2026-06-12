<?php

namespace App\Services;

use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Enums\OutboundMessageTemplate;
use App\Models\Domain\Crm\OutboundMessage;
use App\Models\Domain\Customer\Customer;
use App\Models\User;
use App\Support\WhatsAppLinkBuilder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OutboundMessagingService
{
    public function renderTemplate(OutboundMessageTemplate $template, Customer $customer, ?string $customBody = null): string
    {
        if ($template === OutboundMessageTemplate::Custom && filled($customBody)) {
            return $this->interpolate($customBody, $customer);
        }

        $key = match ($template) {
            OutboundMessageTemplate::InactiveCampaign => 'inactive_campaign',
            OutboundMessageTemplate::FollowUpReminder => 'follow_up_reminder',
            OutboundMessageTemplate::ReturnReminder => 'return_reminder',
            default => 'inactive_campaign',
        };

        $body = config("crm.templates.{$key}", 'Olá {nome}!');

        return $this->interpolate($body, $customer);
    }

    public function queue(
        Customer $customer,
        OutboundChannel $channel,
        string $body,
        OutboundMessageTemplate $template = OutboundMessageTemplate::Custom,
        ?string $campaignRef = null,
        ?CarbonInterface $scheduledAt = null,
        ?User $user = null,
    ): OutboundMessage {
        $phone = trim((string) $customer->telefone);

        if ($phone === '') {
            throw new InvalidArgumentException('Cliente sem telefone para envio.');
        }

        $user ??= auth()->user();

        return OutboundMessage::create([
            'customer_id' => $customer->id,
            'channel' => $channel->value,
            'template' => $template->value,
            'recipient' => $phone,
            'body' => $body,
            'status' => OutboundMessageStatus::Pending->value,
            'campaign_ref' => $campaignRef,
            'scheduled_at' => $scheduledAt ?? now(),
            'created_by' => $user?->id,
        ]);
    }

    public function processPending(int $limit = 50): int
    {
        $messages = OutboundMessage::query()
            ->where('status', OutboundMessageStatus::Pending->value)
            ->where(function ($query) {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($messages as $message) {
            $this->dispatchOne($message);
            $processed++;
        }

        return $processed;
    }

    public function whatsAppLinkFor(OutboundMessage $message): string
    {
        return WhatsAppLinkBuilder::build($message->recipient, $message->body);
    }

    private function dispatchOne(OutboundMessage $message): void
    {
        $driver = config('crm.messaging.driver', 'log');

        try {
            $response = match ($driver) {
                'twilio' => $this->sendViaTwilio($message),
                'link' => $this->sendViaLink($message),
                default => $this->sendViaLog($message),
            };

            $message->update([
                'status' => OutboundMessageStatus::Sent->value,
                'sent_at' => now(),
                'provider_response' => $response,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CRM outbound message failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            $message->update([
                'status' => OutboundMessageStatus::Failed->value,
                'provider_response' => $e->getMessage(),
            ]);
        }
    }

    private function sendViaLog(OutboundMessage $message): string
    {
        Log::info('CRM message (log driver)', [
            'id' => $message->id,
            'channel' => $message->channel,
            'recipient' => $message->recipient,
            'body' => $message->body,
        ]);

        if ($message->channelEnum() === OutboundChannel::Whatsapp) {
            return $this->whatsAppLinkFor($message);
        }

        return 'logged';
    }

    private function sendViaLink(OutboundMessage $message): string
    {
        if ($message->channelEnum() === OutboundChannel::Whatsapp) {
            return $this->whatsAppLinkFor($message);
        }

        return 'sms:requires_twilio_or_manual';
    }

    private function sendViaTwilio(OutboundMessage $message): string
    {
        $sid = config('crm.messaging.twilio.account_sid');
        $token = config('crm.messaging.twilio.auth_token');

        if (! $sid || ! $token) {
            throw new InvalidArgumentException('Twilio não configurado.');
        }

        $from = $message->channelEnum() === OutboundChannel::Whatsapp
            ? config('crm.messaging.twilio.whatsapp_from')
            : config('crm.messaging.twilio.sms_from');

        if (! $from) {
            throw new InvalidArgumentException('Remetente Twilio não configurado.');
        }

        $to = $this->normalizeE164($message->recipient, $message->channelEnum());

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $to,
                'Body' => $message->body,
            ]);

        if (! $response->successful()) {
            throw new InvalidArgumentException('Twilio: '.$response->body());
        }

        return (string) $response->json('sid', 'ok');
    }

    private function normalizeE164(string $phone, OutboundChannel $channel): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $country = config('crm.messaging.default_country_code', '55');

        if ($digits !== '' && ! str_starts_with($digits, $country) && strlen($digits) <= 11) {
            $digits = $country.$digits;
        }

        $prefix = $channel === OutboundChannel::Whatsapp ? 'whatsapp:' : '';

        return $prefix.'+'.$digits;
    }

    private function interpolate(string $body, Customer $customer): string
    {
        return str_replace(
            ['{nome}', '{contato}'],
            [$customer->nome, $customer->contato ?? $customer->nome],
            $body,
        );
    }
}
