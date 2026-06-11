<?php

namespace App\Livewire\Copilot;

use App\Agent\AgentCommandRegistry;
use App\Agent\AgentSessionService;
use App\Agent\Chat\AgentChatOrchestrator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CopilotIndex extends Component
{

  public string $prompt = '';

  public ?int $agentSessionId = null;

  /** @var list<array{role: string, content: string, meta?: array<string, mixed>}> */
  public array $messages = [];

  public ?string $pendingCommand = null;

  /** @var array<string, mixed> */
  public array $pendingInput = [];

  /** @var array<string, mixed>|null */
  public ?array $pendingPreview = null;

  public function mount(AgentSessionService $sessionService): void
  {
    abort_unless(auth()->user()?->can('agent.api'), 403);

    $session = $sessionService->resolve(auth()->user(), 'web');
    $this->agentSessionId = $session->id;

    $this->messages[] = [
      'role' => 'assistant',
      'content' => 'Olá! Sou o copiloto operacional. Posso consultar fichas, avançar locações, faturar e gerenciar OS. Ex.: "resumo financeiro", "retorno LOC-000001", "faturar FAT-000002".',
      'meta' => [
        'actions' => [
          ['label' => 'Resumo financeiro', 'command' => 'finance.summary', 'params' => []],
          ['label' => 'Pendências a faturar', 'command' => 'billing.list_pending', 'params' => []],
        ],
      ],
    ];
  }

  public function sendMessage(AgentChatOrchestrator $orchestrator, AgentSessionService $sessionService): void
  {
    abort_unless(auth()->user()?->can('agent.api'), 403);

    $text = trim($this->prompt);

    if ($text === '') {
      return;
    }

    $this->messages[] = ['role' => 'user', 'content' => $text];
    $this->prompt = '';

    $session = $sessionService->resolve(auth()->user(), 'web', $this->agentSessionId);
    $this->agentSessionId = $session->id;

    $response = $orchestrator->handle($text, auth()->user(), false, $session);

    $this->pushAssistantMessage($response->toArray());

    if ($response->requiresConfirmation) {
      $this->pendingCommand = $response->command;
      $this->pendingInput = $response->commandInput;
      $this->pendingPreview = $response->dryRunPreview;
    } else {
      $this->clearPending();
    }
  }

  public function confirmPending(AgentChatOrchestrator $orchestrator, AgentSessionService $sessionService): void
  {
    abort_unless(auth()->user()?->can('agent.api'), 403);

    if (! $this->pendingCommand) {
      return;
    }

    $session = $sessionService->resolve(auth()->user(), 'web', $this->agentSessionId);

    $response = $orchestrator->executeConfirmed(
      $this->pendingCommand,
      $this->pendingInput,
      auth()->user(),
      $session,
    );

    $this->messages[] = ['role' => 'user', 'content' => '(confirmação)'];
    $this->pushAssistantMessage($response->toArray());
    $this->clearPending();
  }

  public function cancelPending(): void
  {
    $this->clearPending();
    $this->messages[] = [
      'role' => 'assistant',
      'content' => 'Ação cancelada. Pode pedir outra coisa.',
    ];
  }

  /** @param  array<string, mixed>  $params */
  public function runAction(string $command, array $params = [], AgentChatOrchestrator $orchestrator = null, AgentSessionService $sessionService = null): void
  {
    abort_unless(auth()->user()?->can('agent.api'), 403);

    $orchestrator ??= app(AgentChatOrchestrator::class);
    $sessionService ??= app(AgentSessionService::class);

    $this->messages[] = [
      'role' => 'user',
      'content' => "Executar: {$command}",
    ];

    $session = $sessionService->resolve(auth()->user(), 'web', $this->agentSessionId);

    $response = $orchestrator->executeConfirmed($command, $params, auth()->user(), $session);

    $this->pushAssistantMessage($response->toArray());
    $this->clearPending();
  }

  public function render(AgentCommandRegistry $registry): View
  {
    return view('livewire.copilot.copilot-index', [
      'commands' => $registry->manifest(),
    ]);
  }

  /** @param  array<string, mixed>  $payload */
  private function pushAssistantMessage(array $payload): void
  {
    $this->messages[] = [
      'role' => 'assistant',
      'content' => (string) ($payload['reply'] ?? ''),
      'meta' => $payload,
    ];
  }

  private function clearPending(): void
  {
    $this->pendingCommand = null;
    $this->pendingInput = [];
    $this->pendingPreview = null;
  }
}
