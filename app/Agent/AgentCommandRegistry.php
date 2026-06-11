<?php

namespace App\Agent;

use App\Agent\Commands\AbstractAgentCommand;
use App\Agent\Contracts\AgentCommand;
use App\Enums\AgentCommandSurface;
use InvalidArgumentException;

class AgentCommandRegistry
{
  /** @var array<string, AgentCommand> */
  private array $commands = [];

  /** @param  list<class-string<AgentCommand>>  $commandClasses */
  public function registerMany(array $commandClasses): void
  {
    foreach ($commandClasses as $class) {
      $this->register(app($class));
    }
  }

  public function register(AgentCommand $command): void
  {
    $this->commands[$command::name()] = $command;
  }

  public function get(string $name): AgentCommand
  {
    if (! isset($this->commands[$name])) {
      throw new InvalidArgumentException("Comando de agente desconhecido: {$name}");
    }

    return $this->commands[$name];
  }

  public function has(string $name): bool
  {
    return isset($this->commands[$name]);
  }

  /** @return list<array<string, mixed>> */
  public function manifest(): array
  {
    return array_values(array_map(
      fn (AgentCommand $command) => $command->toManifestEntry(),
      $this->commands,
    ));
  }

  /** @return list<string> */
  public function names(): array
  {
    return array_keys($this->commands);
  }

  public function isVisualizationCommand(string $name): bool
  {
    return $this->commandSurface($name) === AgentCommandSurface::Visualization;
  }

  public function isExecutionCommand(string $name): bool
  {
    return $this->commandSurface($name) === AgentCommandSurface::Execution;
  }

  public function commandSurface(string $name): ?AgentCommandSurface
  {
    if (! isset($this->commands[$name])) {
      return null;
    }

    $command = $this->commands[$name];

    if ($command instanceof AbstractAgentCommand) {
      return $command->commandSurface();
    }

    return null;
  }
}
