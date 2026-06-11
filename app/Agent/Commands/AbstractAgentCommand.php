<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Contracts\AgentCommand;
use App\Enums\AgentCommandKind;
use App\Enums\AgentCommandSurface;

abstract class AbstractAgentCommand implements AgentCommand
{
  /** @return list<array{type: string, id: int|string}> */
  public function affectedResources(array $input): array
  {
    return [];
  }

  public function commandKind(): AgentCommandKind
  {
    return AgentCommandKind::Write;
  }

  public function commandSurface(): AgentCommandSurface
  {
    return AgentCommandSurface::Execution;
  }

  public function toManifestEntry(): array
  {
    return [
      'name' => static::name(),
      'description' => static::description(),
      'permission' => $this->permission(),
      'kind' => $this->commandKind()->value,
      'surface' => $this->commandSurface()->value,
      'copilot_mode' => $this->commandSurface()->copilotMode()->value,
      'input_schema' => $this->inputSchema(),
      'affected_resource_types' => $this->declaredResourceTypes(),
    ];
  }

  /** @return list<string> */
  protected function declaredResourceTypes(): array
  {
    return [];
  }

  /** @param  list<array{label: string, command?: string, url?: string, params?: array<string, mixed>, primary?: bool}>  $steps */
  protected function workflowSteps(array $steps): array
  {
    return array_map(function (array $step) {
      if (isset($step['command'], $step['params']) && ! isset($step['url'])) {
        return $step;
      }

      return $step;
    }, $steps);
  }

  protected function success(string $message, array $data = [], array $nextSteps = []): AgentCommandResult
  {
    return AgentCommandResult::success($message, $data, $this->workflowSteps($nextSteps));
  }

  protected function failure(string $message, ?string $errorCode = null): AgentCommandResult
  {
    return AgentCommandResult::failure($message, $errorCode);
  }
}
