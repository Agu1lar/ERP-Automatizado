<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\Domain\Person\Company;
use App\Services\AuditService;

class CompanyObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function created(Company $company): void
    {
        $this->auditService->logModelChange($company, AuditAction::Created);
    }

    public function updated(Company $company): void
    {
        $this->auditService->logModelChange(
            $company,
            AuditAction::Updated,
            $company->getOriginal(),
            $company->toArray(),
        );
    }

    public function deleted(Company $company): void
    {
        $this->auditService->logModelChange($company, AuditAction::Deleted, $company->toArray());
    }
}
