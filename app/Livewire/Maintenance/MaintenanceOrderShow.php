<?php

namespace App\Livewire\Maintenance;

use App\Enums\MaintenanceOrderStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Maintenance\MaintenanceLaborHour;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\MaintenancePart;
use App\Models\User;
use App\Services\MaintenanceOrderService;
use App\Services\PartCatalogService;
use App\Support\FlashMessage;
use App\Support\RentalFichaNavigation;
use App\Support\WorkflowNextStep;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MaintenanceOrderShow extends Component
{
    use AuthorizesRequests;

    public MaintenanceOrder $order;

    public string $diagnostico = '';

    public string $solucao_aplicada = '';

    public string $parecer_tecnico = '';

    public ?int $customer_id = null;

    public string $asset_voltagem = '';

    public string $assinatura_caixa = '';

    public string $assinatura_orcado_por = '';

    public string $assinatura_montado_por = '';

    public ?int $assigned_to = null;

    public string $expected_completion_at = '';

    public string $part_descricao = '';

    public string $part_codigo_peca = '';

    public string $part_codigo_alternativo = '';

    public string $part_quantidade = '1';

    public string $part_valor_unitario = '';

    public string $part_observacao = '';

    public string $part_search = '';

    /** @var list<array<string, mixed>> */
    public array $partSuggestions = [];

    public string $labor_data = '';

    public string $labor_horas = '';

    public string $labor_descricao = '';

    public ?int $labor_user_id = null;

    public string $wait_observacao = '';

    public string $complete_solucao = '';

    public string $cancel_reason = '';

    public bool $showWaitModal = false;

    public bool $showCompleteModal = false;

    public bool $showCancelModal = false;

    public function mount(MaintenanceOrder $order): void
    {
        $this->authorize('view', $order);
        $this->loadOrder($order);
        $this->syncFormFields();
    }

    public function saveTechnicalData(MaintenanceOrderService $service): void
    {
        $this->authorize('update', $this->order);

        $data = $this->validate([
            'diagnostico' => 'nullable|string|max:5000',
            'solucao_aplicada' => 'nullable|string|max:5000',
            'parecer_tecnico' => 'nullable|string|max:8000',
            'customer_id' => 'nullable|exists:customers,id',
            'asset_voltagem' => 'nullable|string|max:50',
            'assinatura_caixa' => 'nullable|string|max:255',
            'assinatura_orcado_por' => 'nullable|string|max:255',
            'assinatura_montado_por' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'expected_completion_at' => 'nullable|date',
        ]);

        try {
            $this->order = $service->updateTechnicalData(
                $this->order,
                $data['diagnostico'] ?: null,
                $data['solucao_aplicada'] ?: null,
                $data['assigned_to'],
                $data['expected_completion_at'] ? Carbon::parse($data['expected_completion_at']) : null,
                $data['parecer_tecnico'] ?: null,
                $data['customer_id'],
                $data['assinatura_caixa'] ?: null,
                $data['assinatura_orcado_por'] ?: null,
                $data['assinatura_montado_por'] ?: null,
                $data['asset_voltagem'],
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('diagnostico', $e->getMessage());

            return;
        }

        $this->loadOrder($this->order);
        $this->syncFormFields();
    }

    public function updatedPartSearch(): void
    {
        $this->searchPartCatalog();
    }

    public function updatedPartCodigoPeca(): void
    {
        $catalog = app(PartCatalogService::class)->findByCode($this->part_codigo_peca);
        if ($catalog) {
            $this->applyCatalogPart($catalog);
        }
    }

    public function pickCatalogPart(int $id): void
    {
        $catalog = \App\Models\Domain\Maintenance\PartCatalogItem::findOrFail($id);
        $this->applyCatalogPart($catalog);
        $this->partSuggestions = [];
    }

    public function addPart(MaintenanceOrderService $service): void
    {
        $this->authorize('update', $this->order);

        if (filled($this->part_codigo_peca) && blank($this->part_descricao)) {
            $catalog = app(PartCatalogService::class)->findByCode($this->part_codigo_peca);
            if ($catalog) {
                $this->applyCatalogPart($catalog);
            }
        }

        $data = $this->validate([
            'part_descricao' => 'required|string|max:255',
            'part_codigo_peca' => 'nullable|string|max:100',
            'part_codigo_alternativo' => 'nullable|string|max:100',
            'part_quantidade' => 'required|numeric|min:0.01',
            'part_valor_unitario' => 'nullable|numeric|min:0',
            'part_observacao' => 'nullable|string|max:500',
        ]);

        try {
            $service->addPart(
                $this->order,
                $data['part_descricao'],
                (float) $data['part_quantidade'],
                $data['part_codigo_peca'] ?: null,
                $data['part_valor_unitario'] !== '' ? (float) $data['part_valor_unitario'] : null,
                $data['part_observacao'] ?: null,
                $data['part_codigo_alternativo'] ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('part_descricao', $e->getMessage());

            return;
        }

        $this->part_descricao = '';
        $this->part_codigo_peca = '';
        $this->part_codigo_alternativo = '';
        $this->part_quantidade = '1';
        $this->part_valor_unitario = '';
        $this->part_observacao = '';
        $this->part_search = '';
        $this->partSuggestions = [];
        $this->loadOrder($this->order);
        $this->flashSuccess('Peça adicionada.');
    }

    public function removePart(int $partId, MaintenanceOrderService $service): void
    {
        $this->authorize('update', $this->order);

        $part = $this->order->parts()->findOrFail($partId);

        try {
            $service->removePart($part);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->loadOrder($this->order);
        $this->flashSuccess('Peça removida.');
    }

    public function addLaborHour(MaintenanceOrderService $service): void
    {
        $this->authorize('update', $this->order);

        $data = $this->validate([
            'labor_data' => 'required|date',
            'labor_horas' => 'required|numeric|min:0.01',
            'labor_descricao' => 'required|string|max:500',
            'labor_user_id' => 'nullable|exists:users,id',
        ]);

        $technician = $data['labor_user_id'] ? User::find($data['labor_user_id']) : null;

        try {
            $service->addLaborHour(
                $this->order,
                $data['labor_descricao'],
                (float) $data['labor_horas'],
                Carbon::parse($data['labor_data']),
                $technician,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('labor_descricao', $e->getMessage());

            return;
        }

        $this->labor_data = now()->toDateString();
        $this->labor_horas = '';
        $this->labor_descricao = '';
        $this->loadOrder($this->order);
        $this->flashSuccess('Horas registradas.');
    }

    public function removeLaborHour(int $hourId, MaintenanceOrderService $service): void
    {
        $this->authorize('update', $this->order);

        $hour = $this->order->laborHours()->findOrFail($hourId);

        try {
            $service->removeLaborHour($hour);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->loadOrder($this->order);
        $this->flashSuccess('Registro de horas removido.');
    }

    public function start(MaintenanceOrderService $service): void
    {
        $this->authorize('operate', $this->order);

        try {
            $this->order = $service->start($this->order);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->loadOrder($this->order);
        $this->flashWorkflowSuccess(
            'OS em execução. Registre peças e horas nesta tela.',
            WorkflowNextStep::maintenanceAfterStart($this->order),
        );
    }

    public function openWaitModal(): void
    {
        $this->authorize('operate', $this->order);
        $this->wait_observacao = '';
        $this->showWaitModal = true;
    }

    public function waitForPart(MaintenanceOrderService $service): void
    {
        $this->authorize('operate', $this->order);

        try {
            $this->order = $service->waitForPart($this->order, $this->wait_observacao ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->addError('wait_observacao', $e->getMessage());

            return;
        }

        $this->showWaitModal = false;
        $this->loadOrder($this->order);
        $this->flashWorkflowSuccess(
            'OS aguardando peça. Retome a execução quando o material chegar.',
            WorkflowNextStep::maintenanceAfterWait($this->order),
        );
    }

    public function resume(MaintenanceOrderService $service): void
    {
        $this->authorize('operate', $this->order);

        try {
            $this->order = $service->resume($this->order);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->loadOrder($this->order);
        $this->flashWorkflowSuccess(
            'Execução retomada. Conclua a OS quando o serviço estiver finalizado.',
            WorkflowNextStep::maintenanceAfterResume($this->order),
        );
    }

    public function openCompleteModal(): void
    {
        $this->authorize('operate', $this->order);
        $this->complete_solucao = $this->order->solucao_aplicada ?? '';
        $this->showCompleteModal = true;
    }

    public function complete(MaintenanceOrderService $service): void
    {
        $this->authorize('operate', $this->order);

        try {
            $this->order = $service->complete($this->order, $this->complete_solucao ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->addError('complete_solucao', $e->getMessage());

            return;
        }

        $this->showCompleteModal = false;
        $this->loadOrder($this->order);
        $this->flashWorkflowSuccess(
            'OS concluída com sucesso.',
            WorkflowNextStep::maintenanceAfterComplete($this->order),
        );
    }

    public function openCancelModal(): void
    {
        $this->authorize('operate', $this->order);
        $this->cancel_reason = '';
        $this->showCancelModal = true;
    }

    public function cancel(MaintenanceOrderService $service): void
    {
        $this->authorize('operate', $this->order);

        $this->validate(['cancel_reason' => 'required|string|max:1000']);

        try {
            $this->order = $service->cancel($this->order, $this->cancel_reason);
        } catch (\InvalidArgumentException $e) {
            $this->addError('cancel_reason', $e->getMessage());

            return;
        }

        $this->showCancelModal = false;
        $this->loadOrder($this->order);
        $this->flashSuccess('OS cancelada.');
    }

    public function render(): View
    {
        return view('livewire.maintenance.maintenance-order-show', [
            'status' => $this->order->statusEnum(),
            'technicians' => User::query()->where('ativo', true)->orderBy('name')->get(),
            'customers' => Customer::query()->where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    private function loadOrder(MaintenanceOrder $order): void
    {
        $this->order = $order->load([
            'asset.equipmentModel.category',
            'rental.customer',
            'customer',
            'openedByUser',
            'assignedToUser',
            'completedByUser',
            'cancelledByUser',
            'parts',
            'laborHours.user',
        ]);
    }

    private function searchPartCatalog(): void
    {
        $this->partSuggestions = app(PartCatalogService::class)
            ->search($this->part_search)
            ->map(fn ($item) => [
                'id' => $item->id,
                'codigo_peca' => $item->codigo_peca,
                'codigo_alternativo' => $item->codigo_alternativo,
                'descricao' => $item->descricao,
                'valor' => $item->valor_unitario_padrao,
            ])
            ->all();
    }

    private function applyCatalogPart(\App\Models\Domain\Maintenance\PartCatalogItem $catalog): void
    {
        $this->part_codigo_peca = $catalog->codigo_peca;
        $this->part_codigo_alternativo = $catalog->codigo_alternativo ?? '';
        $this->part_descricao = $catalog->descricao;
        if ($catalog->valor_unitario_padrao !== null) {
            $this->part_valor_unitario = (string) $catalog->valor_unitario_padrao;
        }
        $this->part_search = $catalog->descricao;
    }

    private function flashSuccess(string $message): void
    {
        session()->flash('success', $message);
        RentalFichaNavigation::flashReturnLink($this->order->rental);
    }

    /** @param  list<array{label: string, url: string, primary?: bool}>  $actions */
    private function flashWorkflowSuccess(string $message, array $actions = []): void
    {
        FlashMessage::success($message, $actions);
        RentalFichaNavigation::flashReturnLink($this->order->rental);
    }

    private function syncFormFields(): void
    {
        $this->diagnostico = $this->order->diagnostico ?? '';
        $this->solucao_aplicada = $this->order->solucao_aplicada ?? '';
        $this->parecer_tecnico = $this->order->parecer_tecnico ?? '';
        $this->customer_id = $this->order->customer_id ?? $this->order->rental?->customer_id;
        $this->asset_voltagem = $this->order->asset->voltagem ?? '';
        $this->assinatura_caixa = $this->order->assinatura_caixa ?? '';
        $this->assinatura_orcado_por = $this->order->assinatura_orcado_por ?? '';
        $this->assinatura_montado_por = $this->order->assinatura_montado_por ?? '';
        $this->assigned_to = $this->order->assigned_to;
        $this->expected_completion_at = $this->order->expected_completion_at?->toDateString() ?? '';
        $this->labor_data = now()->toDateString();
        $this->labor_user_id = auth()->id();
    }
}
