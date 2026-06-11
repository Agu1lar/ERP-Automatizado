<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\Domain\Person\Person;
use App\Services\AuditService;

class PersonObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function created(Person $person): void
    {
        $this->auditService->logModelChange($person, AuditAction::Created);
    }

    public function updated(Person $person): void
    {
        $this->auditService->logModelChange(
            $person,
            AuditAction::Updated,
            $person->getOriginal(),
            $person->toArray(),
        );
    }

    public function deleted(Person $person): void
    {
        $this->auditService->logModelChange($person, AuditAction::Deleted, $person->toArray());
    }
}
