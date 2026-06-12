<?php



namespace App\Agent\Chat;



use App\Agent\AgentCommandExecutor;

use App\Agent\AgentCommandRegistry;

use App\Agent\AgentSessionService;

use App\Agent\Contracts\SupportsDryRun;

use App\Agent\Document\AgentDocumentAnalyzer;

use App\Enums\CopilotMode;

use App\Models\Domain\Agent\AgentSession;

use App\Support\Agent\AgentInputAssessment;
use App\Support\Agent\AgentInputCompletionService;
use App\Support\Agent\CopilotUserMessenger;
use App\Models\User;



class AgentChatOrchestrator
{
  /** @var list<string> */
  private array $dryRunPreviewCommands = [

    'billing.authorize_entry',

    'billing.invoice_entry',

    'billing.process_customer_pending',

    'receivable.mark_paid',

    'customer.create',
    'customer.update',

    'person.create',
    'person.update',
    'company.create',
    'company.update',

    'rental.reserve',
    'rental.cancel',
    'rental.extend',
    'rental.substitute',

    'quote.convert',
    'quote.create',
    'quote.send',
    'quote.cancel',

    'rental.update',
    'rental.transfer_commercial',

    'billing.create_renewal',

    'asset.move_location',
    'asset.transition_status',

    'document.apply_plan',

  ];



  public function __construct(

    private readonly AgentHeuristicParser $parser,

    private readonly AgentLlmDriver $llm,

    private readonly AgentCommandExecutor $executor,

    private readonly AgentCommandRegistry $registry,

    private readonly AgentSessionService $sessionService,

    private readonly AgentDocumentAnalyzer $documentAnalyzer,

    private readonly AgentInputCompletionService $inputCompletion,

    private readonly CopilotUserMessenger $userMessenger,

  ) {}



  public function handle(

    string $message,

    User $user,

    ?AgentSession $session = null,

    ?AgentChatOptions $options = null,

  ): AgentChatResponse {

    $options ??= new AgentChatOptions();

    $message = trim($message);



    if ($message === '' && ! $options->hasAttachments()) {

      return new AgentChatResponse(app(CopilotUserMessenger::class)->emptyPromptGreeting());

    }



    if ($session) {

      $this->sessionService->logMessage($session, 'user', $message, [

        'mode' => $options->mode->value,

        'attachments' => count($options->attachments),

      ]);

    }



    if ($options->hasAttachments()) {

      $documentResponse = $this->handleDocumentMessage($message, $user, $options, $session);



      if ($documentResponse !== null) {

        return $documentResponse;

      }

    }



    if ($session && ($pending = $this->sessionService->getPendingExecution($session))) {

      $resumed = $this->resumePendingExecution($message, $user, $session, $options, $pending);

      if ($resumed !== null) {

        return $resumed;

      }

    }



    [$parsed, $llmDegraded, $llmNotice] = $this->interpretMessage($message, $user, $options->mode, $session?->id);



    return $this->finalizeParsed($parsed, $user, $options, $session, $llmDegraded, $llmNotice);

  }



  /** @return array{0: array{command?: string, input?: array<string, mixed>, reply?: string}, 1: bool, 2: ?string} */
  private function interpretMessage(string $message, User $user, CopilotMode $mode, ?int $sessionId = null): array
  {
    if (! $this->llm->isConfigured()) {
      return [$this->parser->parse($message), false, null];
    }

    $llmResult = $this->llm->interpret($message, $user, $mode, $sessionId);

    if ($llmResult->succeeded && $llmResult->parsed !== null) {
      return [$llmResult->parsed, false, null];
    }

    $parsed = $this->parser->parse($message);

    if ($llmResult->shouldNotifyFallback()) {
      app(\App\Services\AgentLlmUsageService::class)->recordHeuristicFallback(
        $user,
        $llmResult->failureReason,
        $sessionId,
      );

      $notice = $this->userMessenger->llmOperationalFallbackNotice($llmResult->failureReason);

      return [$this->prependDegradationNotice($parsed, $notice), true, $notice];
    }

    return [$parsed, false, null];
  }



  /** @param  array{command?: string, input?: array<string, mixed>, reply?: string}  $parsed */
  private function prependDegradationNotice(array $parsed, string $notice): array
  {
    if (isset($parsed['reply']) && trim((string) $parsed['reply']) !== '') {
      $parsed['reply'] = $notice."\n\n---\n\n".$parsed['reply'];
    } else {
      $parsed['reply'] = $notice;
    }

    return $parsed;
  }



  /** @param  array<string, mixed>  $input */

  public function executeConfirmed(

    string $command,

    array $input,

    User $user,

    ?AgentSession $session = null,

    CopilotMode $mode = CopilotMode::Agent,

  ): AgentChatResponse {

    if ($mode === CopilotMode::Ask && $this->registry->isExecutionCommand($command)) {
      return new AgentChatResponse(
        $this->userMessenger->askModeExecutionBlocked($command),
      );
    }



    if (! $this->registry->has($command)) {

      return new AgentChatResponse($this->userMessenger->unknownAction());

    }



    if ($this->registry->isExecutionCommand($command)) {

      $inputAssessment = $this->inputCompletion->assess($command, $input);

      if (! $inputAssessment->isComplete()) {

        return $this->buildInputRequestResponse($command, $input, $inputAssessment, $session);

      }
    }



    $result = $this->executor->execute($command, $input, $user, $session);



    $userReply = $this->userMessenger->fromCommandResult($result);

    $response = new AgentChatResponse(

      reply: $userReply,

      command: $command,

      commandInput: $input,

      requiresConfirmation: false,

      executed: true,

      result: $result->toArray(),

      actions: $this->mapNextSteps($result->nextSteps),

    );



    if ($session) {

      $this->sessionService->clearPendingExecution($session);

      $this->sessionService->logMessage($session, 'assistant', $userReply, [

        'command' => $command,

        'executed' => true,

        'ok' => $result->ok,

      ]);

    }



    return $response;

  }



  private function handleDocumentMessage(

    string $message,

    User $user,

    AgentChatOptions $options,

    ?AgentSession $session,

  ): ?AgentChatResponse {

    if (! $this->documentAnalyzer->isConfigured()) {

      $reply = 'No momento não consigo ler documentos anexos. '
        .'Peça ao administrador para habilitar a leitura por IA, ou descreva no chat o que precisa fazer.';



      return new AgentChatResponse($reply);

    }



    $analyzeResult = $this->documentAnalyzer->analyze(
      $message !== '' ? $message : 'Analise o documento anexado e extraia os dados relevantes.',
      $options->attachments,
      $options->mode,
      $user,
      $session?->id,
    );



    if ($analyzeResult->shouldNotifyFallback()) {

      $notice = $this->userMessenger->llmOperationalFallbackNotice($analyzeResult->failureReason);

      return new AgentChatResponse(

        reply: $notice."\n\nNão consegui analisar o documento agora. Tente outro formato (PDF, imagem, TXT) ou descreva manualmente o que precisa.",

        llmDegraded: true,

        llmNotice: $notice,

      );

    }



    if ($analyzeResult->plan === null) {

      return new AgentChatResponse(

        'Não consegui analisar o documento. Verifique o formato (PDF, imagem, TXT) ou tente um pedido mais específico.',

      );

    }



    $plan = $analyzeResult->plan;



    $reply = $this->userMessenger->sanitizeReply($plan['reply']);



    if ($options->mode === CopilotMode::Ask) {

      $reply .= "\n\n_Modo Pergunta: nenhum cadastro foi feito. Use **Agente** para executar após revisar._";



      return new AgentChatResponse($reply, result: ['data' => ['extracted' => $plan['extracted']]]);

    }



    $actions = $plan['proposed_actions'];



    if ($actions === []) {

      $reply .= "\n\n_Revise os dados acima. Se quiser cadastrar, peça explicitamente (ex.: \"cadastre este cliente\")._";



      return new AgentChatResponse($reply, result: ['data' => ['extracted' => $plan['extracted']]]);

    }



    if (count($actions) === 1) {

      $action = $actions[0];



      return $this->finalizeParsed([

        'command' => $action['command'],

        'input' => $action['params'] ?? [],

        'reply' => $reply."\n\n**Posso executar:** ".($action['label'] ?? $this->userMessenger->commandLabel($action['command'])),

      ], $user, $options, $session);

    }



    $reply .= "\n\n**Plano proposto:** ".count($actions).' ação(ões). Confirme para executar em sequência.';



    if ($session) {

      $this->sessionService->logMessage($session, 'assistant', $reply, [

        'command' => 'document.apply_plan',

        'requires_confirmation' => true,

      ]);

    }



    return new AgentChatResponse(

      reply: $reply,

      command: 'document.apply_plan',

      commandInput: ['actions' => $actions],

      requiresConfirmation: true,

      dryRunPreview: $this->sanitizeDryRunPreviewForUser([

        'ok' => true,

        'message' => collect($actions)->pluck('label')->filter()->implode(' → '),

      ]),

      result: ['data' => ['extracted' => $plan['extracted']]],

    );

  }



  /** @param  array{command?: string, input?: array<string, mixed>, reply?: string}  $parsed */

  private function finalizeParsed(

    array $parsed,

    User $user,

    AgentChatOptions $options,

    ?AgentSession $session,

    bool $llmDegraded = false,

    ?string $llmNotice = null,

  ): AgentChatResponse {

    if (empty($parsed['command'])) {

      $reply = $parsed['reply'] ?? $this->userMessenger->unknownAction();

      $reply = $this->userMessenger->sanitizeReply($reply);



      if ($session) {

        $this->sessionService->logMessage($session, 'assistant', $reply);

      }



      return $this->withLlmStatus(new AgentChatResponse($reply, actions: $this->quickActions($options->mode)), $llmDegraded, $llmNotice);

    }



    $command = $parsed['command'];

    $input = $parsed['input'] ?? [];



    if (! $this->registry->has($command)) {

      return $this->withLlmStatus(new AgentChatResponse($this->userMessenger->actionUnavailable()), $llmDegraded, $llmNotice);

    }



    if (! $user->can($this->registry->get($command)->permission())) {

      return $this->withLlmStatus(new AgentChatResponse($this->userMessenger->noPermission()), $llmDegraded, $llmNotice);

    }



    if ($options->mode === CopilotMode::Ask && $this->registry->isExecutionCommand($command)) {
      $preview = $this->buildDryRunPreview($command, $input, $user, $session);
      $reply = $this->userMessenger->sanitizeReply(
        $parsed['reply'] ?? 'No modo **Pergunta** só consulto informações — não altero cadastros nem avanço fluxos.'
      )
        ."\n\nMude para **Agente** se quiser que eu **".$this->userMessenger->commandLabel($command).'** por você.';

      if ($preview !== null && ($preview['ok'] ?? false)) {

        $reply .= "\n\n**Prévia do que seria feito:** ".$this->friendlyPreviewMessage($preview);

      }

      return $this->withLlmStatus(new AgentChatResponse($reply), $llmDegraded, $llmNotice);

    }



    if ($options->mode === CopilotMode::Agent && $this->registry->isExecutionCommand($command)) {

      $assessment = $this->inputCompletion->assess($command, $input);

      if (! $assessment->isComplete()) {

        return $this->withLlmStatus(
          $this->buildInputRequestResponse($command, $input, $assessment, $session, $parsed['reply'] ?? null),
          $llmDegraded,
          $llmNotice,
        );

      }

    }



    $needsConfirm = ! $options->confirmed
      && $this->registry->isExecutionCommand($command)
      && $options->mode === CopilotMode::Agent
      && config('agent.chat.require_confirmation', true);



    if ($needsConfirm) {

      $dryRunPreview = $this->buildDryRunPreview($command, $input, $user, $session);

      $reply = $this->userMessenger->sanitizeReply(
        $parsed['reply'] ?? $this->userMessenger->confirmPrompt($command)
      );



      if ($dryRunPreview !== null && ($dryRunPreview['ok'] ?? false)) {

        $reply .= "\n\n**Prévia:** ".$this->friendlyPreviewMessage($dryRunPreview);

      }



      if ($session) {

        $this->sessionService->clearPendingExecution($session);

        $this->sessionService->logMessage($session, 'assistant', $reply, [

          'command' => $command,

          'requires_confirmation' => true,

          'dry_run' => $dryRunPreview,

        ]);

      }



      return $this->withLlmStatus(new AgentChatResponse(

        reply: $reply,

        command: $command,

        commandInput: $input,

        requiresConfirmation: true,

        dryRunPreview: $this->sanitizeDryRunPreviewForUser($dryRunPreview),

      ), $llmDegraded, $llmNotice);

    }



    if ($options->mode === CopilotMode::Ask && $this->registry->isVisualizationCommand($command)) {
      return $this->withLlmStatus($this->executeConfirmed($command, $input, $user, $session, $options->mode), $llmDegraded, $llmNotice);
    }



    if ($options->mode === CopilotMode::Agent) {

      return $this->withLlmStatus($this->executeConfirmed($command, $input, $user, $session, $options->mode), $llmDegraded, $llmNotice);

    }



    return $this->withLlmStatus(new AgentChatResponse('Esta opção não está disponível no modo selecionado.'), $llmDegraded, $llmNotice);
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

  private function quickActions(CopilotMode $mode): array

  {

    $actions = [

      ['label' => 'Resumo financeiro', 'command' => 'finance.summary', 'params' => []],

      ['label' => 'Ocupação da frota', 'command' => 'fleet.analytics', 'params' => ['view' => 'occupancy']],

      ['label' => 'Betoneiras locadas', 'command' => 'rental.list', 'params' => ['status' => 'locado', 'category_query' => 'betoneira', 'limit' => 25]],

      ['label' => 'Situação AC-1001', 'command' => 'asset.get', 'params' => ['asset_codigo' => 'AC-1001']],

    ];



    if ($mode === CopilotMode::Agent) {

      $actions[] = ['label' => 'Pendências a faturar', 'command' => 'billing.list_pending', 'params' => []];

    }



    return $actions;

  }



  /** @param  list<array<string, mixed>>  $steps @return list<array<string, mixed>> */

  private function mapNextSteps(array $steps): array

  {

    return array_map(fn (array $step) => [

      'label' => $step['label'] ?? 'Continuar',

      'command' => $step['command'] ?? null,

      'url' => $step['url'] ?? null,

      'params' => $step['params'] ?? [],

      'primary' => (bool) ($step['primary'] ?? false),

    ], $steps);

  }



  /** @param  array{command: string, input: array<string, mixed>}  $pending */

  private function resumePendingExecution(

    string $message,

    User $user,

    AgentSession $session,

    AgentChatOptions $options,

    array $pending,

  ): ?AgentChatResponse {

    if ($this->isCancelIntent($message)) {

      $this->sessionService->clearPendingExecution($session);

      $reply = $this->userMessenger->cancelledOperation($pending['command']);

      $this->sessionService->logMessage($session, 'assistant', $reply);



      return new AgentChatResponse($reply);

    }



    $command = $pending['command'];

    $input = $this->inputCompletion->mergeFromMessage($message, $pending['input'], $command);

    $assessment = $this->inputCompletion->assess($command, $input);



    if (! $assessment->isComplete()) {

      return $this->buildInputRequestResponse(

        $command,

        $input,

        $assessment,

        $session,

        'Recebi parte dos dados. Ainda falta completar:',

      );

    }



    $this->sessionService->clearPendingExecution($session);



    return $this->finalizeParsed([

      'command' => $command,

      'input' => $input,

      'reply' => 'Informações completas para **'.$assessment->actionLabel.'**. Confirme para executar.',

    ], $user, $options, $session);

  }



  private function buildInputRequestResponse(

    string $command,

    array $input,

    AgentInputAssessment $assessment,

    ?AgentSession $session,

    ?string $intro = null,

  ): AgentChatResponse {

    if ($session) {

      $this->sessionService->setPendingExecution($session, $command, $input);

    }



    $reply = ($intro ? rtrim($intro)."\n\n" : '')

      .$this->inputCompletion->buildRequestMessage($assessment);

    $reply = $this->userMessenger->sanitizeReply($reply);



    if ($session) {

      $this->sessionService->logMessage($session, 'assistant', $reply, [

        'command' => $command,

        'requires_input' => true,

        'input_request' => $assessment->toArray(),

      ]);

    }



    return new AgentChatResponse(

      reply: $reply,

      command: $command,

      commandInput: $input,

      requiresInput: true,

      inputRequest: array_merge($assessment->toArray(), ['partial_input' => $input]),

    );

  }



  private function isCancelIntent(string $message): bool

  {

    $lower = mb_strtolower(trim($message));



    return in_array($lower, [

      'cancelar',

      'cancela',

      'desistir',

      'parar',

      'abortar',

    ], true) || str_starts_with($lower, 'cancelar ');

  }

  private function withLlmStatus(AgentChatResponse $response, bool $llmDegraded, ?string $llmNotice): AgentChatResponse
  {
    if (! $llmDegraded && $llmNotice === null) {
      return $response;
    }

    return new AgentChatResponse(
      reply: $response->reply,
      command: $response->command,
      commandInput: $response->commandInput,
      requiresConfirmation: $response->requiresConfirmation,
      requiresInput: $response->requiresInput,
      inputRequest: $response->inputRequest,
      executed: $response->executed,
      result: $response->result,
      dryRunPreview: $response->dryRunPreview,
      actions: $response->actions,
      llmDegraded: $llmDegraded,
      llmNotice: $llmNotice,
    );
  }

  /** @param  array<string, mixed>|null  $preview @return array<string, mixed>|null */
  private function sanitizeDryRunPreviewForUser(?array $preview): ?array
  {
    if ($preview === null) {
      return null;
    }

    $preview['message'] = $this->friendlyPreviewMessage($preview);

    return $preview;
  }

  /** @param  array<string, mixed>  $preview */
  private function friendlyPreviewMessage(array $preview): string
  {
    if ($preview['ok'] ?? false) {
      return $this->userMessenger->sanitizeReply((string) ($preview['message'] ?? ''));
    }

    return $this->userMessenger->forError(
      (string) ($preview['message'] ?? ''),
      isset($preview['error_code']) ? (string) $preview['error_code'] : null,
    );
  }

}


