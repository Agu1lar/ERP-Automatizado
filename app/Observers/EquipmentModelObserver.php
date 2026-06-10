<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Services\AuditService;

class EquipmentModelObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function created(EquipmentModel $model): void
    {
        $this->auditService->logModelChange($model, AuditAction::Created);
    }

    public function updated(EquipmentModel $model): void
    {
        $this->auditService->logModelChange(
            $model,
            AuditAction::Updated,
            $model->getOriginal(),
            $model->toArray(),
        );
    }

    public function deleted(EquipmentModel $model): void
    {
        $this->auditService->logModelChange($model, AuditAction::Deleted, $model->toArray());
    }
}
