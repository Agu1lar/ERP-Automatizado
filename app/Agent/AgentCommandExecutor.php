<?php

namespace App\Agent;

use App\Agent\Contracts\SupportsDryRun;
use App\Enums\AuditAction;
use App\Models\Domain\Agent\AgentSession;
use App\Models\User;
use App\Services\AuditService;
use InvalidArgumentException;
use Throwable;

class AgentCommandExecutor
{
  public function __construct(
    private readonly AgentCommandRegistry $registry,
    private readonly AuditService $auditService,
    private readonly AgentSessionService $sessionService,
  ) {}

  /** @param  array<string, mixed>  $input */
  public function execute(
    string $name,
    array $input,
    User $user,
    ?AgentSession $session = null,
    bool $dryRun = false,
  ): AgentCommandResult {
    unset($input['dry_run']);

    $command = $this->registry->get($name);

    if (! $user->can($command->permission())) {
      return AgentCommandResult::failure(
        'Usuário sem permissão para executar este comando.',
        'forbidden',
      );
    }

    try {
      $this->validateInput($command->inputSchema(), $input);
    } catch (InvalidArgumentException $e) {
      return AgentCommandResult::failure($e->getMessage(), 'validation_failed');
    }

    try {
      if ($dryRun) {
        if (! $command instanceof SupportsDryRun) {
          return AgentCommandResult::failure(
            'Este comando não suporta simulação (dry-run).',
            'dry_run_unsupported',
          );
        }

        $result = $command->dryRun($input, $user);
      } else {
        $result = $command->execute($input, $user);
      }

      if (! $dryRun) {
        $this->auditService->log(
          AuditAction::Updated,
          'AgentCommand',
          null,
          null,
          [
            'channel' => 'agent_api',
            'command' => $name,
            'ok' => $result->ok,
            'message' => $result->message,
            'input_keys' => array_keys($input),
          ],
          $user,
        );
      }

      $this->sessionService->logCommand($session, $user, $name, $input, $result, $dryRun);

      return $result;
    } catch (InvalidArgumentException $e) {
      return AgentCommandResult::failure($e->getMessage(), 'business_rule');
    } catch (Throwable $e) {
      report($e);

      return AgentCommandResult::failure(
        'Falha interna ao executar o comando.',
        'internal_error',
      );
    }
  }

  /** @param  array<string, mixed>  $schema @param  array<string, mixed>  $input */
  private function validateInput(array $schema, array $input): void
  {
    $required = $schema['required'] ?? [];

    foreach ($required as $field) {
      if (! array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
        throw new InvalidArgumentException("Campo obrigatório ausente: {$field}");
      }
    }

    if (isset($schema['oneOfRequired']) && is_array($schema['oneOfRequired'])) {
      foreach ($schema['oneOfRequired'] as $group) {
        $satisfied = false;

        foreach ($group as $field) {
          if (! empty($input[$field])) {
            $satisfied = true;
            break;
          }
        }

        if (! $satisfied) {
          throw new InvalidArgumentException(
            'Informe um dos campos: '.implode(', ', $group)
          );
        }
      }
    }
  }
}
