<?php

namespace App\Agent;

class AgentCommandResult
{
  /**
   * @param  array<string, mixed>  $data
   * @param  list<array{label: string, command?: string, url?: string, params?: array<string, mixed>, primary?: bool}>  $nextSteps
   */
  public function __construct(
    public readonly bool $ok,
    public readonly string $message,
    public readonly array $data = [],
    public readonly array $nextSteps = [],
    public readonly ?string $errorCode = null,
    public readonly bool $dryRun = false,
  ) {}

  /** @param  list<array{label: string, command?: string, url?: string, params?: array<string, mixed>, primary?: bool}>  $nextSteps */
  public static function success(string $message, array $data = [], array $nextSteps = []): self
  {
    return new self(true, $message, $data, $nextSteps);
  }

  public static function failure(string $message, ?string $errorCode = null): self
  {
    return new self(false, $message, errorCode: $errorCode ?? 'command_failed');
  }

  /** @param  array<string, mixed>  $data */
  public static function preview(string $message, array $data = []): self
  {
    return new self(true, $message, $data, dryRun: true);
  }

  /** @return array<string, mixed> */
  public function toArray(): array
  {
    return [
      'ok' => $this->ok,
      'message' => $this->message,
      'data' => $this->data,
      'next_steps' => $this->nextSteps,
      'error_code' => $this->errorCode,
      'dry_run' => $this->dryRun,
    ];
  }
}
