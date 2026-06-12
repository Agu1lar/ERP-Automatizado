<?php

namespace App\Enums;

enum PaymentChargeStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Received = 'received';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando pagamento',
            self::Confirmed => 'Confirmado',
            self::Received => 'Recebido',
            self::Overdue => 'Vencido',
            self::Cancelled => 'Cancelado',
        };
    }
}
