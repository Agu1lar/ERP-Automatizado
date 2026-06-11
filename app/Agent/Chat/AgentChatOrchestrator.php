<?php

namespace App\Agent\Chat;

use App\Agent\AgentCommandExecutor;
use App\Agent\AgentCommandRegistry;
use App\Agent\AgentSessionService;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\Domain\Agent\AgentSession;
use App\Models\User;

class AgentChatOrchestrator
{
  /** @var list<string> */
  private array $readOnlyCommands = [
    'rental.get',
    'rental.list',
    'customer.search',
    'customer.get',
    'billing.list_pending',
    'finance.summary',
  ];

  /** @var list<string> */
  private array $dryRunPreviewCommands = [
    'billing.authorize_entry',
    'billing.invoice_entry',
    'receivable.mark_paid',
  ];

  public function __construct(
    private readonly AgentHeuristicParser $parser,
    private readonly AgentLlmDriver $llm,
    private readonly AgentCommandExecutor $executor,
    private readonly AgentCommandRegistry $registry,
    private readonly AgentSessionService $sessionService,
  ) {}

  public function handle(
    string $message,
    User $user,
    bool $confirmed = false,
    ?AgentSession $session = null,
  ): AgentChatResponse {
    $message = trim($message);

    if ($message === '') {
      return new AgentChatResponse('Como posso ajudar? Descreva a tarefa ou escolha um atalho abaixo.');
    }

    if ($session) {
      $this->sessionService->logMessage($session, 'user', $message);
    }

    if ($this->llm->isEnabled()) {
      $llmResponse = $this->llm->interpret($message, $user);

      if ($llmResponse !== null) {
        return $this->finalizeParsed($llmResponse, $user, $confirmed, $session);
      }
    }

    $parsed = $this->parser->parse($message);

    return $this->finalizeParsed($parsed, $user, $confirmed, $session);
  }

  /** @param  array<string, mixed>  $input */
  public function executeConfirmed(
    string $command,
    array $input,
    User $user,
    ?AgentSession $session = null,
  ): AgentChatResponse {
    if (! $this->registry->has($command)) {
      return new AgentChatResponse("Comando desconhecido: {$command}");
    }

    $result = $this->executor->execute($command, $input, $user, $session);

    $response = new AgentChatResponse(
      reply: $result->message,
      command: $command,
      commandInput: $input,
      requiresConfirmation: false,
      executed: true,
      result: $result->toArray(),
      actions: $this->mapNextSteps($result->nextSteps),
    );

    if ($session) {
      $this->sessionService->logMessage($session, 'assistant', $result->message, [
        'command' => $command,
        'executed' => true,
        'ok' => $result->ok,
      ]);
    }

    return $response;
  }

  /** @param  array{command?: string, input?: array<string, mixed>, reply?: string}  $parsed */
  private function finalizeParsed(
    array $parsed,
    User $user,
    bool $confirmed,
    ?AgentSession $session,
  ): AgentChatResponse {
    if (empty($parsed['command'])) {
      $reply = $parsed['reply'] ?? 'Não entendi. Tente mencionar o código (LOC-, OS-, FAT-, TIT-) ou use os atalhos.';

      if ($session) {
        $this->sessionService->logMessage($session, 'assistant', $reply);
      }

      return new AgentChatResponse($reply, actions: $this->quickActions());
    }

    $command = $parsed['command'];
    $input = $parsed['input'] ?? [];

    if (! $this->registry->has($command)) {
      return new AgentChatResponse("Comando {$command} não está disponível.");
    }

    if (! $user->can($this->registry->get($command)->permission())) {
      return new AgentChatResponse('Você não tem permissão para esta ação.');
    }

    $needsConfirm = ! $confirmed
      && ! in_array($command, $this->readOnlyCommands, true)
      && config('agent.chat.require_confirmation', true);

    if ($needsConfirm) {
      $dryRunPreview = $this->buildDryRunPreview($command, $input, $user, $session);
      $reply = $parsed['reply'] ?? "Posso executar **{$command}**. Confirme para continuar.";

      if ($dryRunPreview !== null && ($dryRunPreview['ok'] ?? false)) {
        $reply .= "\n\n**Prévia:** ".$dryRunPreview['message'];
      }

      if ($session) {
        $this->sessionService->logMessage($session, 'assistant', $reply, [
          'command' => $command,
          'requires_confirmation' => true,
          'dry_run' => $dryRunPreview,
        ]);
      }

      return new AgentChatResponse(
        reply: $reply,
        command: $command,
        commandInput: $input,
        requiresConfirmation: true,
        dryRunPreview: $dryRunPreview,
      );
    }

    return $this->executeConfirmed($command, $input, $user, $session);
  }

  /** @param  array<string, mixed>  $input @return array<string, mixed>|null */
  private function buildDryRunPreview(string $command, array $input, User $user, ?AgentSession $session): ?array
  {
    if (! in_array($command, $this->dryRunPreviewCommands, true)) {
      return null;
    }

    $cmd = $this->registry->get($command);

    if (! $cmd instanceof SupportsDryRun) {
      return null;
    }

    $preview = $this->executor->execute($command, $input, $user, $session, dryRun: true);

    return $preview->toArray();
  }

  /** @return list<array{label: string, command?: string, params?: array<string, mixed>}> */
  private function quickActions(): array
  {
    return [
      ['label' => 'Resumo financeiro', 'command' => 'finance.summary', 'params' => []],
      ['label' => 'Pendências a faturar', 'command' => 'billing.list_pending', 'params' => []],
      ['label' => 'Locações locadas', 'command' => 'rental.list', 'params' => ['status' => 'locado', 'limit' => 10]],
    ];
  }

  /** @param  list<array<string, mixed>>  $steps @return list<array<string, mixed>> */
  private function mapNextSteps(array $steps): array
  {
    return array_map(fn (array $step) => [
      'label' => $step['label'] ?? 'Continuar',
      'command' => $step['command'] ?? null,
      'url' => $step['url'] ?? null,
      'params' => $step['params'] ?? [],
    ], $steps);
  }
}
