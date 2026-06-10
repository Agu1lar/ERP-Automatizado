<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Domain\Audit\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        AuditAction $action,
        string $entity,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?User $user = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => ($user ?? auth()->user())?->id,
            'entidade' => $entity,
            'entidade_id' => $entityId,
            'acao' => $action->value,
            'antes_json' => $before,
            'depois_json' => $after,
            'ip' => Request::ip(),
        ]);
    }

    public function logModelChange(Model $model, AuditAction $action, ?array $before = null, ?array $after = null): AuditLog
    {
        return $this->log(
            $action,
            class_basename($model),
            $model->getKey(),
            $before,
            $after ?? $model->toArray(),
        );
    }
}
