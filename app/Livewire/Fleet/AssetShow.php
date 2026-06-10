<?php

namespace App\Livewire\Fleet;

use App\Enums\AssetStatus;
use App\Jobs\GenerateAssetQrCodeJob;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Services\AssetMovementService;
use App\Services\AssetStatusService;
use App\Services\AttachmentService;
use App\Services\MaintenanceOrderService;
use App\Services\PreventiveMaintenanceService;
use App\Services\QrCodeService;
use App\Support\AssetTimeline;
use App\Support\FichaCompleteness;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class AssetShow extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public Asset $asset;

    public bool $showStatusModal = false;

    public bool $showLocationModal = false;

    public string $new_status = '';

    public string $motivo = '';

    public string $nova_localizacao = '';

    public string $motivo_movimentacao = '';

    public $attachmentFile;

    public string $activeTab = 'resumo';

    public string $ficha_descricao = '';

    public string $ficha_horimetro = '';

    public string $ficha_serie = '';

    public string $ficha_voltagem = '';

    public string $ficha_observacoes = '';

    public string $ficha_localizacao = '';

    public string $ficha_valor_compra = '';

    public string $ficha_data_compra = '';

    public function mount(Asset $asset): void
    {
        $this->authorize('view', $asset);
        $this->loadAsset($asset);
        $this->syncFichaFields();
    }

    public function openPreventiveOrder(int $ruleId): void
    {
        $this->authorize('create', MaintenanceOrder::class);

        $service = app(PreventiveMaintenanceService::class);
        $rule = $service->rulesForModel($this->asset->equipment_model_id)->firstWhere('id', $ruleId);

        if (! $rule) {
            session()->flash('error', 'Regra preventiva não encontrada para este equipamento.');

            return;
        }

        $order = app(MaintenanceOrderService::class)->openPreventive($this->asset, $rule);
        $this->loadAsset($this->asset->fresh());

        session()->flash('success', "OS preventiva {$order->codigo} aberta.");
        $this->redirect(route('maintenance.show', $order), navigate: true);
    }

    public function saveFicha(): void
    {
        $this->authorize('update', $this->asset);

        $rules = [
            'ficha_descricao' => 'nullable|string|max:5000',
            'ficha_horimetro' => 'nullable|numeric|min:0',
            'ficha_serie' => 'nullable|string|max:255',
            'ficha_voltagem' => 'nullable|string|max:50',
            'ficha_observacoes' => 'nullable|string|max:5000',
            'ficha_localizacao' => 'nullable|string|max:255',
        ];

        if (auth()->user()->can('updatePurchaseValue', $this->asset)) {
            $rules['ficha_valor_compra'] = 'nullable|numeric|min:0';
            $rules['ficha_data_compra'] = 'nullable|date';
        }

        $data = $this->validate($rules);

        $previousLocation = $this->asset->localizacao;

        $updates = [
            'descricao' => $data['ficha_descricao'] ?: null,
            'horimetro' => $data['ficha_horimetro'] !== '' ? $data['ficha_horimetro'] : null,
            'serie' => $data['ficha_serie'] ?: null,
            'voltagem' => $data['ficha_voltagem'] ?: null,
            'observacoes' => $data['ficha_observacoes'] ?: null,
        ];

        if (auth()->user()->can('updatePurchaseValue', $this->asset)) {
            $updates['valor_compra'] = ($data['ficha_valor_compra'] ?? '') !== '' ? $data['ficha_valor_compra'] : null;
            $updates['data_compra'] = ($data['ficha_data_compra'] ?? '') !== '' ? $data['ficha_data_compra'] : null;
        }

        $this->asset->update($updates);

        $newLocation = trim($data['ficha_localizacao'] ?? '');
        if ($newLocation !== '' && $newLocation !== ($previousLocation ?? '')) {
            app(AssetMovementService::class)->moveLocation($this->asset, $newLocation, 'Atualização na ficha');
        }

        $this->loadAsset($this->asset);
        $this->syncFichaFields();
    }

    public function openStatusModal(): void
    {
        $this->authorize('changeStatus', $this->asset);
        $this->showStatusModal = true;
        $this->new_status = '';
        $this->motivo = '';
    }

    public function openLocationModal(): void
    {
        $this->authorize('changeStatus', $this->asset);
        $this->showLocationModal = true;
        $this->nova_localizacao = $this->asset->localizacao ?? '';
        $this->motivo_movimentacao = '';
    }

    public function changeStatus(AssetStatusService $statusService): void
    {
        $this->authorize('changeStatus', $this->asset);

        $this->validate([
            'new_status' => 'required|string',
            'motivo' => 'nullable|string|max:1000',
        ]);

        try {
            $statusService->transition(
                $this->asset,
                AssetStatus::from($this->new_status),
                $this->motivo ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('new_status', $e->getMessage());

            return;
        }

        $this->loadAsset($this->asset);
        $this->showStatusModal = false;
        session()->flash('success', 'Status atualizado com sucesso.');
    }

    public function moveLocation(AssetMovementService $movementService): void
    {
        $this->authorize('changeStatus', $this->asset);

        $this->validate([
            'nova_localizacao' => 'required|string|max:255',
            'motivo_movimentacao' => 'nullable|string|max:1000',
        ]);

        try {
            $movementService->moveLocation(
                $this->asset,
                $this->nova_localizacao,
                $this->motivo_movimentacao ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('nova_localizacao', $e->getMessage());

            return;
        }

        $this->loadAsset($this->asset);
        $this->showLocationModal = false;
        session()->flash('success', 'Localização atualizada com sucesso.');
    }

    public function reprocessQrCode(QrCodeService $qrCodeService): void
    {
        $this->authorize('update', $this->asset);

        $qrCodeService->markPending($this->asset);
        GenerateAssetQrCodeJob::dispatch($this->asset->id);

        $this->loadAsset($this->asset);
        session()->flash('success', 'Geração de QR Code enfileirada.');
    }

    public function uploadAttachment(AttachmentService $attachmentService): void
    {
        $this->authorize('manageAttachments', $this->asset);

        $this->validate([
            'attachmentFile' => 'required|file|max:10240',
        ]);

        try {
            $attachmentService->store($this->asset, $this->attachmentFile);
        } catch (\InvalidArgumentException $e) {
            $this->addError('attachmentFile', $e->getMessage());

            return;
        }

        $this->attachmentFile = null;
        $this->asset->load('attachments.user');
        session()->flash('success', 'Anexo enviado com sucesso.');
    }

    public function deleteAttachment(int $attachmentId, AttachmentService $attachmentService): void
    {
        $this->authorize('manageAttachments', $this->asset);

        $attachment = $this->asset->attachments()->findOrFail($attachmentId);
        $attachmentService->delete($attachment);
        $this->asset->load('attachments.user');
        session()->flash('success', 'Anexo removido.');
    }

    public function render(): View
    {
        $allowedTransitions = app(AssetStatusService::class)->allowedTransitionsFor($this->asset);

        $preventiveService = app(PreventiveMaintenanceService::class);
        $preventiveRules = $preventiveService->rulesForModel($this->asset->equipment_model_id);
        $preventiveStatuses = $preventiveRules->map(
            fn ($rule) => $preventiveService->statusForAssetRule($this->asset, $rule)
        );

        return view('livewire.fleet.asset-show', [
            'allowedTransitions' => $allowedTransitions,
            'currentStatus' => $this->asset->statusEnum(),
            'qrStatus' => $this->asset->qrCodeStatusEnum(),
            'hasQrImage' => app(QrCodeService::class)->existsOnDisk($this->asset),
            'timeline' => AssetTimeline::for($this->asset),
            'activeRental' => $this->asset->activeRental()?->load('customer'),
            'activeMaintenanceOrder' => $this->asset->activeMaintenanceOrder(),
            'fichaWarnings' => FichaCompleteness::assetWarnings($this->asset),
            'fichaComplete' => FichaCompleteness::isAssetComplete($this->asset),
            'maintenanceHistory' => $preventiveService->historyForAsset($this->asset),
            'preventiveStatuses' => $preventiveStatuses,
        ]);
    }

    private function syncFichaFields(): void
    {
        $this->ficha_descricao = $this->asset->descricao ?? '';
        $this->ficha_horimetro = $this->asset->horimetro !== null ? (string) $this->asset->horimetro : '';
        $this->ficha_serie = $this->asset->serie ?? '';
        $this->ficha_voltagem = $this->asset->voltagem ?? '';
        $this->ficha_observacoes = $this->asset->observacoes ?? '';
        $this->ficha_localizacao = $this->asset->localizacao ?? '';
        $this->ficha_valor_compra = $this->asset->valor_compra !== null ? (string) $this->asset->valor_compra : '';
        $this->ficha_data_compra = $this->asset->data_compra?->format('Y-m-d') ?? '';
    }

    private function loadAsset(Asset $asset): void
    {
        $this->asset = $asset->load([
            'equipmentModel.category',
            'statusHistories.user',
            'movements.user',
            'attachments.user',
            'rentals.customer',
            'rentals.reservedByUser',
            'rentals.checkoutByUser',
            'rentals.returnedByUser',
            'maintenanceOrders.openedByUser',
            'maintenanceOrders.completedByUser',
        ]);
    }
}
