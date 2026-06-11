<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Models\User;

class FinanceSummaryCommand extends AbstractAgentCommand
{
  public function __construct(
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'finance.summary';
  }

  public static function description(): string
  {
    return 'Resumo financeiro operacional: a receber na semana, ciclos vencidos e inadimplência.';
  }

  public function permission(): string
  {
    return 'finance.view';
  }

  public function inputSchema(): array
  {
    return [
      'type' => 'object',
      'properties' => [],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    return $this->success(
      'Resumo financeiro da empresa ativa.',
      $this->contextBuilder->systemSnapshot(),
    );
  }
}
