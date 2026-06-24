<?php



namespace App\Livewire\Copilot;



use App\Agent\AgentCommandRegistry;

use App\Agent\AgentSessionService;

use App\Agent\Chat\AgentChatOptions;

use App\Agent\Chat\AgentChatOrchestrator;

use App\Agent\Chat\AgentChatResponse;

use App\Agent\Chat\AgentLlmDriver;

use App\Enums\CopilotMode;

use App\Models\Domain\Agent\AgentTask;
use App\Services\AgentTaskService;

use App\Support\CopilotPageContext;
use App\Support\CopilotScreenContextResolver;
use App\Support\Agent\CopilotUserMessenger;

use Illuminate\Contracts\View\View;

use Illuminate\Support\Facades\Storage;

use Livewire\Component;

use Livewire\WithFileUploads;



class CopilotPanel extends Component

{

    use WithFileUploads;



    public bool $isOpen = false;



    public string $prompt = '';



    /** ask | agent */

    public string $mode = 'ask';



    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */

    public $attachment = null;



    /** @var list<array{path: string, mime: string, original_name: string}> */

    public array $queuedAttachments = [];



    public ?int $agentSessionId = null;



    /** @var list<array{role: string, content: string, meta?: array<string, mixed>}> */

    public array $messages = [];



    public ?string $pendingCommand = null;

    public ?string $pendingActionLabel = null;



    /** @var array<string, mixed> */

    public array $pendingInput = [];



    /** @var array<string, mixed>|null */

    public ?array $pendingPreview = null;



    public bool $requiresInput = false;



    /** @var array<string, mixed>|null */

    public ?array $inputRequest = null;

    public bool $llmDegraded = false;

    public ?string $llmDegradationNotice = null;



    public string $pageRoute = '';



    public string $pageUrl = '';



    public string $pageLabel = '';



    public ?string $pageDetail = null;



    public string $pageSummary = '';

    /** @var array<string, mixed> */
    public array $pageParameters = [];



    public bool $enabled = false;



    public function mount(): void

    {

        $this->enabled = auth()->user()?->can('agent.api') ?? false;



        if (! $this->enabled) {

            return;

        }



        if (request()->boolean('copilot')) {

            $this->isOpen = true;

        }



        $this->bootstrapSession();

        $this->refreshPageContext();

    }



    public function togglePanel(): void

    {

        $this->isOpen = ! $this->isOpen;

    }



    public function closePanel(): void

    {

        $this->isOpen = false;

    }



    public function setMode(string $mode): void

    {

        if (in_array($mode, ['ask', 'agent'], true)) {

            $this->mode = $mode;

        }

    }



    public function updatedAttachment(): void

    {

        abort_unless($this->enabled, 403);



        $maxKb = (int) config('agent.chat.max_attachment_kb', 10240);



        $this->validate([

            'attachment' => 'required|file|max:'.$maxKb.'|mimes:pdf,jpg,jpeg,png,webp,txt,csv,doc,docx,xls,xlsx',

        ]);



        if (count($this->queuedAttachments) >= (int) config('agent.chat.max_attachments', 3)) {

            $this->addError('attachment', 'Máximo de 3 anexos por mensagem.');

            $this->attachment = null;



            return;

        }



        $path = $this->attachment->store('agent-intake/'.auth()->id(), 'local');



        $this->queuedAttachments[] = [

            'path' => $path,

            'mime' => (string) $this->attachment->getMimeType(),

            'original_name' => (string) $this->attachment->getClientOriginalName(),

        ];



        $this->attachment = null;

    }



    public function removeAttachment(int $index): void

    {

        if (! isset($this->queuedAttachments[$index])) {

            return;

        }



        Storage::disk('local')->delete($this->queuedAttachments[$index]['path']);

        unset($this->queuedAttachments[$index]);

        $this->queuedAttachments = array_values($this->queuedAttachments);

    }



    public function syncPageContext(string $url): void

    {

        $this->refreshPageContext($url);

    }



    public function refreshPageContext(?string $url = null): void

    {

        if (! $this->enabled) {

            return;

        }



        $context = $url

            ? CopilotPageContext::fromUrl($url)

            : CopilotPageContext::fromRequest(request());



        $this->pageRoute = $context['route'];

        $this->pageUrl = $context['url'];

        $this->pageLabel = $context['label'];

        $this->pageDetail = $context['detail'];

        $this->pageSummary = $context['summary'];

        $this->pageParameters = $context['parameters'] ?? [];
    }



    public function sendMessage(
        AgentChatOrchestrator $orchestrator,
        AgentSessionService $sessionService,
        CopilotScreenContextResolver $screenContext,
    ): void

    {

        abort_unless($this->enabled, 403);



        $text = trim($this->prompt);



        if ($text === '' && $this->queuedAttachments === []) {

            return;

        }



        $display = $text;

        if ($this->queuedAttachments !== []) {

            $names = collect($this->queuedAttachments)->pluck('original_name')->implode(', ');

            $display = ($display !== '' ? $display."\n\n" : '')."📎 {$names}";

        }



        $this->messages[] = ['role' => 'user', 'content' => $display];

        $this->prompt = '';



        $session = $sessionService->resolve(auth()->user(), 'web', $this->agentSessionId);

        $this->agentSessionId = $session->id;



        $messageForAgent = $screenContext->formatForAgent(
            auth()->user(),
            $this->pageSummary,
            $this->pageRoute,
            $this->pageUrl,
            $this->pageParameters,
        );

        $messageForAgent = $messageForAgent !== '' ? "{$messageForAgent}\n\n{$text}" : $text;



        $options = new AgentChatOptions(

            mode: CopilotMode::from($this->mode),

            attachments: $this->queuedAttachments,

            userMessage: $text,

        );



        $response = $orchestrator->handle($messageForAgent, auth()->user(), $session, $options);



        $this->queuedAttachments = [];

        $this->pushAssistantMessage($response->toArray());

        $this->syncLlmStatus($response);



        if ($response->requiresInput && $this->mode === 'agent') {

            $this->pendingCommand = $response->command;

            $this->pendingActionLabel = $this->resolveActionLabel($response);

            $this->pendingInput = $response->commandInput;

            $this->inputRequest = $response->inputRequest;

            $this->requiresInput = true;

            $this->pendingPreview = null;

        } elseif ($response->requiresConfirmation && $this->mode === 'agent') {

            $this->pendingCommand = $response->command;

            $this->pendingActionLabel = $this->resolveActionLabel($response);

            $this->pendingInput = $response->commandInput;

            $this->pendingPreview = $response->actionPreview ?? $response->dryRunPreview;

            $this->requiresInput = false;

            $this->inputRequest = null;

        } else {

            $this->clearPending();

        }



        $this->dispatch('copilot-scroll-bottom');

    }



    public function confirmPending(AgentChatOrchestrator $orchestrator, AgentSessionService $sessionService): void

    {

        abort_unless($this->enabled, 403);



        if ($this->mode !== 'agent' || ! $this->pendingCommand) {

            return;

        }



        $session = $sessionService->resolve(auth()->user(), 'web', $this->agentSessionId);



        $response = $orchestrator->executeConfirmed(

            $this->pendingCommand,

            $this->pendingInput,

            auth()->user(),

            $session,

            CopilotMode::Agent,

        );



        $this->messages[] = ['role' => 'user', 'content' => '(confirmação)'];

        $this->pushAssistantMessage($response->toArray());

        $this->syncLlmStatus($response);

        $this->clearPending();

        $this->dispatch('copilot-scroll-bottom');

    }



    public function cancelPending(AgentSessionService $sessionService): void

    {

        if ($this->agentSessionId) {

            $session = $sessionService->resolve(auth()->user(), 'web', $this->agentSessionId);

            $sessionService->clearPendingExecution($session);

        }

        $this->clearPending();

        $this->messages[] = [

            'role' => 'assistant',

            'content' => 'Cancelado. Manda a próxima quando quiser.',

        ];

    }



    /** @param  array<string, mixed>  $taskData */
    public function onTaskProgress(int $taskId, array $taskData): void
    {
        foreach ($this->messages as $index => $message) {
            if (($message['meta']['task']['id'] ?? null) !== $taskId) {
                continue;
            }

            $this->messages[$index]['meta']['task'] = $taskData;

            $status = (string) ($taskData['status'] ?? '');
            $terminal = ['completed', 'failed', 'conflict', 'cancelled'];

            if (in_array($status, $terminal, true) && empty($message['meta']['task_terminal_announced'])) {
                $suffix = match ($status) {
                    'completed' => "\n\n✓ **Tarefa concluída.**",
                    'failed' => "\n\n✗ **Falhou:** ".($taskData['error_message'] ?? 'erro desconhecido'),
                    'conflict' => "\n\n⚠ **Conflito:** ".($taskData['conflict_reason'] ?? 'recurso alterado manualmente'),
                    'cancelled' => "\n\n_Tarefa cancelada._",
                    default => '',
                };
                $this->messages[$index]['content'] .= $suffix;
                $this->messages[$index]['meta']['task_terminal_announced'] = true;
            }

            break;
        }

        $this->dispatch('copilot-scroll-bottom');
    }

    public function cancelBackgroundTask(int $taskId, AgentTaskService $taskService): void
    {
        abort_unless($this->enabled, 403);

        $task = AgentTask::query()
            ->whereKey($taskId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $task) {
            return;
        }

        $cancelled = $taskService->cancel($task);
        $this->onTaskProgress($taskId, $cancelled->toAgentArray());
        $this->dispatch('copilot-stop-task', taskId: $taskId);
    }

    public function refreshActiveTasks(): void
    {
        foreach ($this->messages as $message) {
            $taskId = (int) ($message['meta']['task']['id'] ?? 0);
            $status = (string) ($message['meta']['task']['status'] ?? '');

            if ($taskId === 0 || ! in_array($status, ['queued', 'running'], true)) {
                continue;
            }

            $task = AgentTask::query()
                ->whereKey($taskId)
                ->where('user_id', auth()->id())
                ->first();

            if (! $task) {
                continue;
            }

            $this->onTaskProgress($taskId, $task->toAgentArray());
        }
    }

    public function hasActiveBackgroundTasks(): bool
    {
        foreach ($this->messages as $message) {
            $status = (string) ($message['meta']['task']['status'] ?? '');

            if (in_array($status, ['queued', 'running'], true)) {
                return true;
            }
        }

        return false;
    }

    public function queuePendingInBackground(AgentTaskService $taskService, AgentSessionService $sessionService): void

    {

        abort_unless($this->enabled, 403);



        if ($this->mode !== 'agent' || ! $this->pendingCommand) {

            return;

        }



        $session = $sessionService->resolve(auth()->user(), 'web', $this->agentSessionId);



        $steps = $this->pendingCommand === 'document.apply_plan'

            ? ($this->pendingInput['actions'] ?? [])

            : [['command' => $this->pendingCommand, 'params' => $this->pendingInput, 'label' => $this->pendingActionLabel ?? $this->pendingCommand]];



        $task = $taskService->queue(

            auth()->user(),

            $steps,

            'Copiloto: '.($this->pendingActionLabel ?? 'ação'),

            $session,

        )->fresh();



        $this->messages[] = ['role' => 'user', 'content' => '(executar em background)'];

        $this->messages[] = [

            'role' => 'assistant',

            'content' => "**Tarefa enfileirada** ({$task->uuid}).\n\nExecutando em background — alterações manuais nos mesmos registros podem gerar **conflito**.",

            'meta' => ['task' => $task->toAgentArray()],

        ];

        $this->onTaskProgress($task->id, $task->toAgentArray());



        $this->clearPending();

        $this->dispatch('copilot-watch-task', taskId: $task->id);

        $this->dispatch('copilot-scroll-bottom');

    }



    /** @param  array<string, mixed>  $params */

    public function runAction(string $command, array $params = [], ?AgentChatOrchestrator $orchestrator = null, ?AgentSessionService $sessionService = null): void

    {

        abort_unless($this->enabled, 403);



        $orchestrator ??= app(AgentChatOrchestrator::class);

        $sessionService ??= app(AgentSessionService::class);



        $actionLabel = app(CopilotUserMessenger::class)->commandLabel($command);

        $this->messages[] = [

            'role' => 'user',

            'content' => $actionLabel,

        ];



        $session = $sessionService->resolve(auth()->user(), 'web', $this->agentSessionId);

        $copilotMode = CopilotMode::from($this->mode);



        if ($copilotMode === CopilotMode::Ask) {

            $options = new AgentChatOptions(mode: CopilotMode::Ask);

            $response = $orchestrator->handle(

                $actionLabel,

                auth()->user(),

                $session,

                $options,

            );

        } else {

            $response = $orchestrator->executeConfirmed($command, $params, auth()->user(), $session, CopilotMode::Agent);

        }



        $this->pushAssistantMessage($response->toArray());

        $this->syncLlmStatus($response);

        $this->clearPending();

        $this->dispatch('copilot-scroll-bottom');

    }



    public function render(AgentCommandRegistry $registry): View

    {

        if (! $this->enabled) {

            return view('livewire.copilot.copilot-panel-empty');

        }



        return view('livewire.copilot.copilot-panel', [

            'commands' => $registry->manifest(),

            'llmEnabled' => app(\App\Agent\Document\AgentDocumentAnalyzer::class)->isAvailable(),

            'llmConfigured' => app(\App\Agent\Document\AgentDocumentAnalyzer::class)->isConfigured(),

            'hasActiveBackgroundTasks' => $this->hasActiveBackgroundTasks(),

        ]);

    }



    private function bootstrapSession(): void

    {

        $session = app(AgentSessionService::class)->resolve(auth()->user(), 'web');

        $this->agentSessionId = $session->id;



        $this->messages[] = [

            'role' => 'assistant',

            'content' => app(CopilotUserMessenger::class)->welcomeMessage(),

            'meta' => [

                'actions' => [

                    ['label' => 'Betoneiras locadas', 'command' => 'rental.list', 'params' => ['status' => 'locado', 'category_query' => 'betoneira', 'limit' => 25]],

                    ['label' => 'Situação AC-1001', 'command' => 'asset.get', 'params' => ['asset_codigo' => 'AC-1001']],

                    ['label' => 'Resumo financeiro', 'command' => 'finance.summary', 'params' => []],

                ],

            ],

        ];

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



    private function syncLlmStatus(AgentChatResponse $response): void

    {

        if ($response->llmDegraded) {

            $this->llmDegraded = true;

            $this->llmDegradationNotice = $response->llmNotice;

            return;

        }

        if (app(AgentLlmDriver::class)->isConfigured()) {

            $this->llmDegraded = false;

            $this->llmDegradationNotice = null;

        }

    }



    private function clearPending(): void

    {

        $this->pendingCommand = null;

        $this->pendingActionLabel = null;

        $this->pendingInput = [];

        $this->pendingPreview = null;

        $this->requiresInput = false;

        $this->inputRequest = null;

    }



    private function resolveActionLabel(object $response): ?string
    {
        if ($response->inputRequest['action_label'] ?? null) {
            return (string) $response->inputRequest['action_label'];
        }

        if ($response->command) {
            return app(CopilotUserMessenger::class)->commandLabel($response->command);
        }

        return null;
    }

}


