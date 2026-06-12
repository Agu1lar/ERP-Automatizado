<?php

namespace App\Contracts;

use App\Data\PaymentChargeResult;
use App\Enums\PaymentMethod;
use App\Models\Domain\Finance\ReceivableTitle;

interface PaymentGateway
{
    public function createCharge(ReceivableTitle $title, PaymentMethod $method): PaymentChargeResult;

    public function refreshCharge(ReceivableTitle $title): PaymentChargeResult;
}
