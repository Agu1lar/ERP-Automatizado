<?php

namespace App\Enums;

enum OutboundMessageTemplate: string
{
    case FollowUpReminder = 'follow_up_reminder';
    case InactiveCampaign = 'inactive_campaign';
    case ReturnReminder = 'return_reminder';
    case Delinquency = 'delinquency';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::FollowUpReminder => 'Lembrete de follow-up',
            self::InactiveCampaign => 'Campanha inativos',
            self::ReturnReminder => 'Lembrete de retorno',
            self::Delinquency => 'Cobrança',
            self::Custom => 'Personalizada',
        };
    }
}
