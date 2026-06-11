<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class FinanceSummaryCommand extends AbstractReadAgentCommand
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
    $snapshot = $this->contextBuilder->systemSnapshot();
    $finance = $snapshot['finance'] ?? [];
    $week = $finance['receivable_this_week'] ?? [];
    $delinq = $finance['delinquency'] ?? [];

    $message = "**Resumo financeiro**\n\n"
      .'• A receber esta semana: **R$ '.number_format((float) ($week['total'] ?? 0), 2, ',', '.')."** ({$week['quantidade']} título(s))\n"
      .'• Ciclos de faturamento vencidos: **'.(int) ($finance['billing_cycle_due_count'] ?? 0)."**\n"
      .'• Inadimplência: **R$ '.number_format((float) ($delinq['total_atrasado'] ?? 0), 2, ',', '.').'** — **'.(int) ($delinq['clientes'] ?? 0)."** cliente(s)\n\n"
      .'Use os atalhos para abrir a tela correspondente ou peça para **faturar** / **baixar** um código específico.';

    return $this->success(
      $message,
      $snapshot,
      [
        ['label' => 'Títulos a receber', 'url' => CopilotNavigationLinks::financeReceivables(), 'primary' => true],
        ['label' => 'Fila a faturar', 'url' => CopilotNavigationLinks::billingQueue()],
        ['label' => 'Inadimplência', 'url' => route('finance.delinquency')],
      ],
    );
  }
}
