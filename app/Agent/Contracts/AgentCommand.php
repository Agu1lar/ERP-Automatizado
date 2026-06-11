<?php

namespace App\Agent\Contracts;

use App\Agent\AgentCommandResult;
use App\Models\User;

interface AgentCommand
{
  public static function name(): string;

  public static function description(): string;

  /** @return array<string, mixed> JSON-Schema-like definition */
  public function inputSchema(): array;

  public function permission(): string;

  /** @param  array<string, mixed>  $input */
  public function execute(array $input, User $user): AgentCommandResult;

  /** @return array<string, mixed> */
  public function toManifestEntry(): array;
}
