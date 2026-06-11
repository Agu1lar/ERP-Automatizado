<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Contracts\AgentCommand;

abstract class AbstractAgentCommand implements AgentCommand
{
  public function toManifestEntry(): array
  {
    return [
      'name' => static::name(),
      'description' => static::description(),
      'permission' => $this->permission(),
      'input_schema' => $this->inputSchema(),
    ];
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
