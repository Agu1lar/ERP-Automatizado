<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\Domain\Customer\Customer;
use App\Services\AuditService;

class CustomerObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function created(Customer $customer): void
    {
        $this->auditService->logModelChange($customer, AuditAction::Created);
    }

    public function updated(Customer $customer): void
    {
        $this->auditService->logModelChange(
            $customer,
            AuditAction::Updated,
            $customer->getOriginal(),
            $customer->toArray(),
        );
    }

    public function deleted(Customer $customer): void
    {
        $this->auditService->logModelChange($customer, AuditAction::Deleted, $customer->toArray());
    }
}
