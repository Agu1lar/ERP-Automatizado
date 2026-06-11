<?php

namespace App\Agent\Chat;

class AgentChatResponse
{
  /**
   * @param  list<array{label: string, command?: string, url?: string, params?: array<string, mixed>}>  $actions
   */
  public function __construct(
    public readonly string $reply,
    public readonly ?string $command = null,
    /** @var array<string, mixed> */
    public readonly array $commandInput = [],
    public readonly bool $requiresConfirmation = false,
    public readonly bool $requiresInput = false,
    /** @var array<string, mixed>|null */
    public readonly ?array $inputRequest = null,
    public readonly bool $executed = false,
    /** @var array<string, mixed>|null */
    public readonly ?array $result = null,
    /** @var array<string, mixed>|null */
    public readonly ?array $dryRunPreview = null,
    public readonly array $actions = [],
    public readonly bool $llmDegraded = false,
    public readonly ?string $llmNotice = null,
  ) {}

  /** @return array<string, mixed> */
  public function toArray(): array
  {
    return [
      'reply' => $this->reply,
      'command' => $this->command,
      'command_input' => $this->commandInput,
      'requires_confirmation' => $this->requiresConfirmation,
      'requires_input' => $this->requiresInput,
      'input_request' => $this->inputRequest,
      'executed' => $this->executed,
      'result' => $this->result,
      'dry_run_preview' => $this->dryRunPreview,
      'actions' => $this->actions,
      'llm_degraded' => $this->llmDegraded,
      'llm_notice' => $this->llmNotice,
    ];
  }
}
