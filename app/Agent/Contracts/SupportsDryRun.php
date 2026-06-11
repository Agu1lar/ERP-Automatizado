<?php

namespace App\Agent\Contracts;

use App\Agent\AgentCommandResult;
use App\Models\User;

interface SupportsDryRun
{
    /** @param  array<string, mixed>  $input */
    public function dryRun(array $input, User $user): AgentCommandResult;
}
