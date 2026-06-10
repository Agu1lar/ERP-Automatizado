<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditService;

class UserObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function updated(User $user): void
    {
        if ($user->wasChanged(['password', 'remember_token'])) {
            return;
        }

        $this->auditService->logModelChange(
            $user,
            AuditAction::Updated,
            array_intersect_key($user->getOriginal(), array_flip(['name', 'email', 'ativo'])),
            ['name' => $user->name, 'email' => $user->email, 'ativo' => $user->ativo],
        );
    }
}
