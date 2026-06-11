<?php

namespace App\Livewire\Rental;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\PaymentMethod;
use App\Enums\RentalChecklistType;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalStatus;
use App\Livewire\Concerns\ManagesBillingPayment;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\Domain\Rental\RentalItem;
use App\Models\User;
use App\Rules\ValidCpfCnpj;
use App\Services\AttachmentService;
use App\Services\MaintenanceOrderService;
use App\Services\ReceivableTitleService;
use App\Services\RentalBillingService;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use App\Support\BillingQueueReportQuery;
use App\Support\FichaCompleteness;
use App\Support\FlashMessage;
use App\Enums\RentalBillingQueueStatus;
use App\Support\RentalFichaNavigation;
use App\Support\RentalWorkflow;
use App\Support\WorkflowNextStep;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class RentalShow extends Component
{
    use AuthorizesRequests, ManagesBillingPayment, WithFileUploads;

    public Rental $rental;

    public bool $showCheckoutModal = false;

    public bool $showReturnModal = false;

    public bool $showCompleteModal = false;

    public bool $showCancelModal = false;

    public bool $showExtendModal = false;

    public string $extend_expected_return_at = '';

    public string $extend_pricing_period = '';

    /** @var array<string, bool> */
    public array $checklistItems = [];

    public string $checklistObservacoes = '';

    public string $cancelReason = '';

    public bool $sendToMaintenance = false;

    public string $motivoManutencao = '';

    public string $ficha_descricao = '';

    public string $ficha_horimetro_saida = '';

    public string $ficha_horimetro_retorno = '';

    public string $ficha_observacoes = '';

    public string $ficha_valor_faturamento = '';

    public string $ficha_local_obra = '';

    public string $ficha_valor_frete_entrega = '';

    public string $ficha_valor_frete_recolhida = '';

    public string $asset_descricao = '';

    public string $asset_horimetro = '';

    public string $asset_serie = '';

    public string $customer_nome = '';

    public string $customer_cpf_cnpj = '';

    public string $customer_contato = '';

    public string $customer_telefone = '';

    public string $customer_email = '';

    public string $customer_endereco = '';

    public string $rental_operating_company_id = '';

    public bool $showTransferCommercialModal = false;

    public string $transfer_commercial_user_id = '';

    public bool $showSubstituteModal = false;

    public string $substitute_asset_search = '';

    public string $substitute_asset_id = '';

    public string $substitute_motivo = '';

    public bool $showOpenOsModal = false;

    public string $os_tipo = '';

    public string $os_descricao = '';

    public bool $os_impeditiva = true;

    public string $os_valor_indenizacao = '';

    public string $inspectionOutcome = 'ok';

    public $attachmentFile;

    public string $activeTab = 'dados';

    public string $billing_cycle_days = '28';

    public string $billing_min_amount = '';

    /** @var array<int, string> */
    public array $item_indenizacao = [];

    public bool $showPostFlowPrompt = false;

    public string $postFlowMessage = '';

    public ?string $postFlowGoUrl = null;

    public string $postFlowGoLabel = 'Abrir';

    public bool $gerar_fatura_na_saida = true;

    public string $checkout_title_vencimento = '';

    /** @var array<int, string> */
    public array $billing_title_vencimento = [];

    public ?int $highlightBillingEntryId = null;

    public function mount(Rental $rental): void
    {
        $this->authorize('view', $rental);
        $this->loadRental($rental);
        $this->syncFichaFields();
        $this->initBillingPaymentDefaults();

        $this->rental_operating_company_id = $rental->operating_company_id ?? '';
    }

    public function saveFicha(): void
    {
        $this->authorize('updateFicha', $this->rental);

        $rules = [
            'ficha_descricao' => 'nullable|string|max:5000',
            'ficha_horimetro_saida' => 'nullable|numeric|min:0',
            'ficha_horimetro_retorno' => 'nullable|numeric|min:0',
            'ficha_observacoes' => 'nullable|string|max:2000',
            'ficha_valor_faturamento' => 'nullable|numeric|min:0',
            'ficha_local_obra' => 'nullable|string|max:2000',
            'ficha_valor_frete_entrega' => 'nullable|numeric|min:0',
            'ficha_valor_frete_recolhida' => 'nullable|numeric|min:0',
            'asset_descricao' => 'nullable|string|max:5000',
            'asset_horimetro' => 'nullable|numeric|min:0',
            'asset_serie' => 'nullable|string|max:255',
        ];

        if ($this->canEditCustomer()) {
            $rules['customer_nome'] = 'required|string|max:255';
            $rules['customer_cpf_cnpj'] = ['required', 'string', 'max:20', new ValidCpfCnpj];
            $rules['customer_contato'] = 'nullable|string|max:255';
            $rules['customer_telefone'] = 'nullable|string|max:30';
            $rules['customer_email'] = 'nullable|email|max:255';
            $rules['customer_endereco'] = 'nullable|string';
        }

        $data = $this->validate($rules);

        $this->rental->update([
            'ficha_descricao' => $data['ficha_descricao'] ?: null,
            'horimetro_saida' => $data['ficha_horimetro_saida'] !== '' ? $data['ficha_horimetro_saida'] : null,
            'horimetro_retorno' => $data['ficha_horimetro_retorno'] !== '' ? $data['ficha_horimetro_retorno'] : null,
            'observacoes' => $data['ficha_observacoes'] ?: null,
            'valor_faturamento' => $data['ficha_valor_faturamento'] !== '' ? $data['ficha_valor_faturamento'] : null,
            'valor_frete_entrega' => $data['ficha_valor_frete_entrega'] !== '' ? $data['ficha_valor_frete_entrega'] : null,
            'valor_frete_recolhida' => $data['ficha_valor_frete_recolhida'] !== '' ? $data['ficha_valor_frete_recolhida'] : null,
        ]);

        app(RentalBillingService::class)->syncContractRateFromRental($this->rental->fresh());

        app(RentalService::class)->updateLocalObra(
            $this->rental,
            $data['ficha_local_obra'] !== '' ? $data['ficha_local_obra'] : null,
        );

        if ($this->canEditAsset()) {
            $this->rental->asset->update([
                'descricao' => $data['asset_descricao'] ?: null,
                'horimetro' => $data['asset_horimetro'] !== '' ? $data['asset_horimetro'] : null,
                'serie' => $data['asset_serie'] ?: null,
            ]);
        }

        if ($this->canEditCustomer()) {
            $this->rental->customer->update([
                'nome' => $data['customer_nome'],
                'cpf_cnpj' => preg_replace('/\D/', '', $data['customer_cpf_cnpj']),
                'contato' => $data['customer_contato'] ?: null,
                'telefone' => $data['customer_telefone'] ?: null,
                'email' => $data['customer_email'] ?: null,
                'endereco' => $data['customer_endereco'] ?: null,
            ]);
        }

        $this->loadRental($this->rental);
        $this->syncFichaFields();
    }

    public function saveRentalField(string $field): void
    {
        $this->authorize('updateFicha', $this->rental);

        match ($field) {
            'ficha_descricao' => $this->rental->update([
                'ficha_descricao' => $this->validateOnly('ficha_descricao', ['ficha_descricao' => 'nullable|string|max:5000'])['ficha_descricao'] ?: null,
            ]),
            'ficha_horimetro_saida' => $this->rental->update([
                'horimetro_saida' => ($v = $this->validateOnly('ficha_horimetro_saida', ['ficha_horimetro_saida' => 'nullable|numeric|min:0'])['ficha_horimetro_saida']) !== '' ? $v : null,
            ]),
            'ficha_horimetro_retorno' => $this->rental->update([
                'horimetro_retorno' => ($v = $this->validateOnly('ficha_horimetro_retorno', ['ficha_horimetro_retorno' => 'nullable|numeric|min:0'])['ficha_horimetro_retorno']) !== '' ? $v : null,
            ]),
            'ficha_observacoes' => $this->rental->update([
                'observacoes' => $this->validateOnly('ficha_observacoes', ['ficha_observacoes' => 'nullable|string|max:2000'])['ficha_observacoes'] ?: null,
            ]),
            'ficha_valor_faturamento' => tap($this->rental->update([
                'valor_faturamento' => ($v = $this->validateOnly('ficha_valor_faturamento', ['ficha_valor_faturamento' => 'nullable|numeric|min:0'])['ficha_valor_faturamento']) !== '' ? $v : null,
            ]), fn () => app(RentalBillingService::class)->syncContractRateFromRental($this->rental->fresh())),
            'ficha_valor_frete_entrega' => $this->rental->update([
                'valor_frete_entrega' => ($v = $this->validateOnly('ficha_valor_frete_entrega', ['ficha_valor_frete_entrega' => 'nullable|numeric|min:0'])['ficha_valor_frete_entrega']) !== '' ? $v : null,
            ]),
            'ficha_valor_frete_recolhida' => $this->rental->update([
                'valor_frete_recolhida' => ($v = $this->validateOnly('ficha_valor_frete_recolhida', ['ficha_valor_frete_recolhida' => 'nullable|numeric|min:0'])['ficha_valor_frete_recolhida']) !== '' ? $v : null,
            ]),
            'ficha_local_obra' => app(RentalService::class)->updateLocalObra(
                $this->rental,
                ($v = $this->validateOnly('ficha_local_obra', ['ficha_local_obra' => 'nullable|string|max:2000'])['ficha_local_obra']) !== '' ? $v : null,
            ),
            'asset_descricao' => $this->canEditAsset() ? $this->rental->asset->update([
                'descricao' => $this->validateOnly('asset_descricao', ['asset_descricao' => 'nullable|string|max:5000'])['asset_descricao'] ?: null,
            ]) : null,
            'asset_horimetro' => $this->canEditAsset() ? $this->rental->asset->update([
                'horimetro' => ($v = $this->validateOnly('asset_horimetro', ['asset_horimetro' => 'nullable|numeric|min:0'])['asset_horimetro']) !== '' ? $v : null,
            ]) : null,
            'asset_serie' => $this->canEditAsset() ? $this->rental->asset->update([
                'serie' => $this->validateOnly('asset_serie', ['asset_serie' => 'nullable|string|max:255'])['asset_serie'] ?: null,
            ]) : null,
            'customer_nome' => $this->saveCustomerField('nome', 'customer_nome', ['required', 'string', 'max:255']),
            'customer_cpf_cnpj' => $this->saveCustomerField('cpf_cnpj', 'customer_cpf_cnpj', ['required', 'string', 'max:20', new ValidCpfCnpj], true),
            'customer_contato' => $this->saveCustomerField('contato', 'customer_contato', ['nullable', 'string', 'max:255']),
            'customer_telefone' => $this->saveCustomerField('telefone', 'customer_telefone', ['nullable', 'string', 'max:30']),
            'customer_email' => $this->saveCustomerField('email', 'customer_email', ['nullable', 'email', 'max:255']),
            'customer_endereco' => $this->saveCustomerField('endereco', 'customer_endereco', ['nullable', 'string']),
            default => abort(404),
        };

        $this->loadRental($this->rental->fresh());
        $this->syncFichaFields();
    }

    /** @param list<string|ValidCpfCnpj> $rules */
    private function saveCustomerField(string $column, string $property, array $rules, bool $stripDocument = false): void
    {
        if (! $this->canEditCustomer()) {
            return;
        }

        $value = $this->validateOnly($property, [$property => $rules])[$property];

        if ($stripDocument) {
            $value = preg_replace('/\D/', '', $value);
        }

        $this->rental->customer->update([$column => $value ?: null]);
    }

    public function changeRentalCompany(): void
    {
        $this->authorize('update', $this->rental);

        $this->validate(['rental_operating_company_id' => 'nullable|integer|exists:operating_companies,id']);

        $this->rental->update([
            'operating_company_id' => $this->rental_operating_company_id !== '' ? (int) $this->rental_operating_company_id : null,
        ]);

        $this->loadRental($this->rental->fresh());
        session()->flash('success', 'Empresa da locação atualizada.');
    }

    public function openCheckoutModal(): void
    {
        $this->authorize('operate', $this->rental);
        $this->resetChecklist(RentalChecklistType::Saida);
        $this->gerar_fatura_na_saida = true;
        $cycleDays = max(1, (int) ($this->rental->billing_cycle_days ?: 28));
        $periodEnd = ($this->rental->checkout_at ?? now())->copy()->startOfDay()->addDays($cycleDays - 1);
        $this->checkout_title_vencimento = ($this->rental->expected_return_at ?? $periodEnd->copy()->addDays(7))->toDateString();
        $this->showCheckoutModal = true;
    }

    public function openReturnModal(): void
    {
        $this->authorize('operate', $this->rental);
        $this->resetChecklist(RentalChecklistType::Retorno);
        $this->showReturnModal = true;
    }

    public function openCompleteModal(): void
    {
        $this->authorize('operate', $this->rental);
        $this->inspectionOutcome = 'ok';
        $this->sendToMaintenance = false;
        $this->motivoManutencao = '';
        $this->showCompleteModal = true;
    }

    public function openCancelModal(): void
    {
        $this->authorize('cancel', $this->rental);
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function openExtendModal(): void
    {
        $this->authorize('operate', $this->rental);
        $this->extend_expected_return_at = $this->rental->expected_return_at
            ? $this->rental->expected_return_at->copy()->addDays(7)->toDateString()
            : now()->addDays(7)->toDateString();
        $this->extend_pricing_period = $this->rental->pricing_period ?? '';
        $this->showExtendModal = true;
    }

    public function extendRental(RentalService $rentalService): void
    {
        $this->authorize('operate', $this->rental);

        $data = $this->validate([
            'extend_expected_return_at' => 'required|date|after:'.$this->rental->expected_return_at?->toDateString(),
        ]);

        $period = filled($this->extend_pricing_period)
            ? RentalPricingPeriod::from($this->extend_pricing_period)
            : null;

        try {
            $this->rental = $rentalService->extend(
                $this->rental,
                \Carbon\Carbon::parse($data['extend_expected_return_at']),
                $period,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('extend_expected_return_at', $e->getMessage());

            return;
        }

        $this->showExtendModal = false;
        $this->loadRental($this->rental);
        $this->syncFichaFields();
        session()->flash('success', 'Locação prorrogada e valor recalculado.');
    }

    public function checkout(RentalService $rentalService, RentalBillingService $billingService): void
    {
        $this->authorize('operate', $this->rental);

        try {
            $this->rental = $rentalService->checkout(
                $this->rental,
                $this->checklistItems,
                $this->checklistObservacoes ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('checklist', $e->getMessage());

            return;
        }

        $this->showCheckoutModal = false;
        $this->loadRental($this->rental);
        $this->applyCheckoutTitleDueDate();

        if ($this->gerar_fatura_na_saida && auth()->user()->can('create', ReceivableTitle::class)) {
            $entry = $this->rental->pendingBillingEntries()->first();

            if ($entry) {
                try {
                    $entry = $billingService->authorizeAndInvoice($entry);
                    $this->loadRental($this->rental->fresh());
                    $this->activeTab = 'faturamento';
                    $this->finishInvoiceAction($entry->fresh(['customer', 'rental', 'receivableTitle']), 'Saída registrada e fatura gerada.');

                    return;
                } catch (\InvalidArgumentException $e) {
                    $this->activeTab = 'faturamento';
                    session()->flash('error', 'Saída registrada, mas a fatura não foi gerada: '.$e->getMessage());

                    return;
                }
            }
        }

        if ($this->rental->pendingBillingEntries()->exists()) {
            $this->activeTab = 'faturamento';
            FlashMessage::success(
                'Saída registrada. Título criado — ajuste o vencimento e autorize o faturamento quando estiver pronto.',
                WorkflowNextStep::rentalAfterCheckout($this->rental),
            );
        } else {
            session()->flash('success', 'Saída registrada com sucesso.');
        }
    }

    public function registerReturn(RentalService $rentalService): void
    {
        $this->authorize('operate', $this->rental);

        try {
            $this->rental = $rentalService->registerReturn(
                $this->rental,
                $this->checklistItems,
                $this->checklistObservacoes ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('checklist', $e->getMessage());

            return;
        }

        $this->showReturnModal = false;
        $this->loadRental($this->rental);
        FlashMessage::success(
            'Retorno registrado. Próximo passo: concluir a inspeção.',
            WorkflowNextStep::rentalAfterReturn($this->rental),
        );
    }

    public function completeInspection(RentalService $rentalService): void
    {
        $this->authorize('operate', $this->rental);

        $this->sendToMaintenance = $this->inspectionOutcome === 'maintenance';

        $this->validate([
            'inspectionOutcome' => 'required|in:ok,maintenance,indenizacao',
            'motivoManutencao' => in_array($this->inspectionOutcome, ['maintenance', 'indenizacao'], true)
                ? 'required|string|max:1000'
                : 'nullable',
            'os_valor_indenizacao' => $this->inspectionOutcome === 'indenizacao'
                ? 'required|numeric|min:0.01'
                : 'nullable',
        ]);

        try {
            if ($this->inspectionOutcome === 'indenizacao') {
                $this->rental = $rentalService->completeInspectionWithIndemnity(
                    $this->rental,
                    $this->motivoManutencao,
                    (float) $this->os_valor_indenizacao,
                );
            } else {
                $this->rental = $rentalService->completeInspection(
                    $this->rental,
                    $this->sendToMaintenance,
                    $this->motivoManutencao ?: null,
                );
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('complete', $e->getMessage());

            return;
        }

        $this->showCompleteModal = false;
        $this->loadRental($this->rental);

        $message = $this->inspectionOutcome === 'indenizacao'
            ? 'Inspeção concluída. OS de indenização aberta e cobrança registrada na ficha.'
            : ($this->inspectionOutcome === 'maintenance'
                ? 'Inspeção concluída. OS de retorno aberta e locação finalizada.'
                : 'Inspeção concluída. Locação finalizada.');

        if (in_array($this->inspectionOutcome, ['maintenance', 'indenizacao'], true)) {
            $order = $this->rental->maintenanceOrders()->latest('id')->first();
            $this->offerPostFlowNavigation(
                $message,
                $order ? route('maintenance.show', $order) : null,
                $order ? "Abrir OS {$order->codigo}" : null,
            );
        } else {
            session()->flash('success', $message);
        }
    }

    public function openMaintenanceOrderModal(): void
    {
        $this->authorize('create', MaintenanceOrder::class);
        $this->os_tipo = MaintenanceOrderType::Corretiva->value;
        $this->os_descricao = '';
        $this->os_impeditiva = ! in_array($this->rental->statusEnum(), [RentalStatus::Locado, RentalStatus::Reservado], true);
        $this->os_valor_indenizacao = '';
        $this->showOpenOsModal = true;
    }

    public function createMaintenanceOrder(MaintenanceOrderService $maintenanceOrderService): void
    {
        $this->authorize('create', MaintenanceOrder::class);

        $data = $this->validate([
            'os_tipo' => 'required|string',
            'os_descricao' => 'required|string|max:5000',
            'os_impeditiva' => 'boolean',
            'os_valor_indenizacao' => $this->os_tipo === MaintenanceOrderType::Indenizacao->value
                ? 'required|numeric|min:0.01'
                : 'nullable|numeric|min:0',
        ]);

        $tipo = MaintenanceOrderType::from($data['os_tipo']);
        $impeditiva = $data['os_impeditiva'];

        if (in_array($this->rental->statusEnum(), [RentalStatus::Locado, RentalStatus::Reservado], true)) {
            $impeditiva = false;
        }

        try {
            $order = $maintenanceOrderService->open(
                $this->rental->asset,
                $data['os_descricao'],
                $tipo,
                impeditiva: $impeditiva,
                rental: $this->rental,
            );

            if ($tipo === MaintenanceOrderType::Indenizacao && filled($data['os_valor_indenizacao'])) {
                app(RentalBillingService::class)->queueIndemnity(
                    $this->rental->fresh(),
                    (float) $data['os_valor_indenizacao'],
                    "Indenização — OS {$order->codigo}: {$data['os_descricao']}",
                    invoiceImmediately: true,
                );
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('os_descricao', $e->getMessage());

            return;
        }

        $this->showOpenOsModal = false;
        $this->loadRental($this->rental->fresh());
        $this->offerPostFlowNavigation(
            "OS {$order->codigo} aberta e vinculada a esta locação.",
            route('maintenance.show', $order),
            "Abrir OS {$order->codigo}",
        );
    }

    public function stayOnRentalFicha(): void
    {
        if ($this->postFlowMessage !== '') {
            session()->flash('success', $this->postFlowMessage);
        }

        $this->resetPostFlowPrompt();
    }

    public function goToPostFlowDestination(): void
    {
        $url = $this->postFlowGoUrl;
        $message = $this->postFlowMessage;

        $this->resetPostFlowPrompt();

        if ($url === null) {
            return;
        }

        session()->flash('success', $message);
        RentalFichaNavigation::flashReturnLink($this->rental);

        $this->redirect($url, navigate: true);
    }

    public function saveBillingTitleDueDate(int $entryId, ReceivableTitleService $receivableTitleService): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $entry = $this->rental->billingQueueEntries()->with('receivableTitle')->findOrFail($entryId);

        if (! in_array($entry->statusEnum(), [RentalBillingQueueStatus::Pendente, RentalBillingQueueStatus::Autorizado], true)) {
            session()->flash('error', 'Vencimento só pode ser alterado antes de gerar a fatura.');

            return;
        }

        if (! $entry->receivableTitle) {
            session()->flash('error', 'Esta pendência ainda não possui título vinculado.');

            return;
        }

        $data = $this->validate([
            "billing_title_vencimento.{$entryId}" => 'required|date',
        ]);

        try {
            $receivableTitleService->updateOpenDueDate(
                $entry->receivableTitle,
                Carbon::parse($data['billing_title_vencimento'][$entryId]),
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError("billing_title_vencimento.{$entryId}", $e->getMessage());

            return;
        }

        $this->loadRental($this->rental->fresh());
        session()->flash('success', 'Vencimento do título atualizado.');
    }

    public function saveBillingSettings(RentalBillingService $billingService): void
    {
        $this->authorize('updateFicha', $this->rental);

        $data = $this->validate([
            'billing_cycle_days' => 'required|integer|min:1|max:365',
            'billing_min_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $billingService->updateBillingSettings(
                $this->rental,
                (int) $data['billing_cycle_days'],
                ($data['billing_min_amount'] ?? '') !== '' ? (float) $data['billing_min_amount'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->loadRental($this->rental->fresh());
        $this->syncFichaFields();
        session()->flash('success', 'Configuração de faturamento atualizada.');
    }

    public function invoicePendingBilling(RentalBillingService $billingService): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $entry = $this->rental->pendingBillingEntries()->first();

        if (! $entry) {
            session()->flash('error', 'Não há pendências de faturamento nesta ficha.');

            return;
        }

        try {
            $entry = $billingService->authorizeAndInvoice($entry);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->loadRental($this->rental->fresh());
        $this->activeTab = 'faturamento';
        $this->finishInvoiceAction($entry->fresh(['customer', 'rental', 'receivableTitle']));
    }

    public function authorizeBillingEntry(int $entryId, RentalBillingService $billingService): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $entry = $this->rental->billingQueueEntries()->findOrFail($entryId);

        try {
            $billingService->authorizeEntry($entry);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->loadRental($this->rental->fresh());
        $this->highlightBillingEntryId = $entryId;
        $this->activeTab = 'faturamento';

        FlashMessage::success("Fatura {$entry->fresh()->codigo} autorizada.", [
            ['label' => 'Ver fila autorizada', 'url' => route('finance.billing-queue', ['status' => RentalBillingQueueStatus::Autorizado->value]), 'primary' => true],
        ]);
    }

    public function invoiceBillingEntry(int $entryId, RentalBillingService $billingService): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $entry = $this->rental->billingQueueEntries()->findOrFail($entryId);

        try {
            $entry = $billingService->authorizeAndInvoice($entry);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->loadRental($this->rental->fresh());
        $this->activeTab = 'faturamento';
        $this->finishInvoiceAction($entry->fresh(['customer', 'rental', 'receivableTitle']));
    }

    public function dismissBillingHighlight(): void
    {
        $this->highlightBillingEntryId = null;
    }

    protected function afterBillingPaymentConfirmed(ReceivableTitle $title): void
    {
        $this->loadRental($this->rental->fresh());

        $entryId = RentalBillingQueueEntry::query()
            ->where('receivable_title_id', $title->id)
            ->value('id');

        if ($entryId) {
            $this->highlightBillingEntryId = (int) $entryId;
        }
    }

    private function finishInvoiceAction(RentalBillingQueueEntry $entry, ?string $message = null): void
    {
        $this->highlightBillingEntryId = $entry->id;
        FlashMessage::success($message ?? "Fatura {$entry->codigo} gerada e título a receber confirmado.", [
            ['label' => 'Ver faturamentos', 'url' => route('finance.billing-queue', ['status' => RentalBillingQueueStatus::Faturado->value]), 'primary' => true],
            ['label' => 'Títulos a receber', 'url' => route('finance.receivables', ['q' => $entry->receivableTitle?->codigo ?? ''])],
        ]);
        $this->dispatch('billing-download', url: route('finance.billing.pdf', $entry));
    }

    public function generateRenewalBilling(RentalBillingService $billingService): void
    {
        $this->authorize('updateFicha', $this->rental);

        try {
            $entry = $billingService->createRenewalIfDue($this->rental);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        if (! $entry) {
            session()->flash('error', 'Renovação ainda não está no vencimento ou já existe pendência aberta.');

            return;
        }

        $this->loadRental($this->rental->fresh());
        $this->activeTab = 'faturamento';
        $this->highlightBillingEntryId = $entry->id;

        FlashMessage::success("Renovação {$entry->codigo} incluída na fila a faturar.", [
            ['label' => 'Ver na fila a faturar', 'url' => route('finance.billing-queue'), 'primary' => true],
        ]);
    }

    public function saveItemIndemnity(int $itemId, RentalBillingService $billingService): void
    {
        $this->authorize('updateFicha', $this->rental);

        $item = $this->rental->items()->findOrFail($itemId);
        $value = $this->item_indenizacao[$itemId] ?? '';

        $billingService->updateItemIndemnity(
            $item,
            $value !== '' ? (float) $value : null,
        );

        $this->loadRental($this->rental->fresh());
        $this->syncFichaFields();
        session()->flash('success', 'Valor de indenização atualizado.');
    }

    public function uploadAttachment(AttachmentService $attachmentService): void
    {
        $this->authorize('manageAttachments', $this->rental);

        $this->validate([
            'attachmentFile' => 'required|file|max:10240',
        ]);

        try {
            $attachmentService->store($this->rental, $this->attachmentFile, tipo: 'anexo_locacao');
        } catch (\InvalidArgumentException $e) {
            $this->addError('attachmentFile', $e->getMessage());

            return;
        }

        $this->attachmentFile = null;
        $this->loadRental($this->rental);
        session()->flash('success', 'Anexo enviado com sucesso.');
    }

    public function deleteAttachment(int $attachmentId, AttachmentService $attachmentService): void
    {
        $this->authorize('manageAttachments', $this->rental);

        $attachment = $this->rental->attachments()->findOrFail($attachmentId);
        $attachmentService->delete($attachment);
        $this->loadRental($this->rental);
        session()->flash('success', 'Anexo removido.');
    }

    public function openSubstituteModal(): void
    {
        $this->authorize('operate', $this->rental);
        $this->substitute_asset_search = '';
        $this->substitute_asset_id = '';
        $this->substitute_motivo = '';
        $this->showSubstituteModal = true;
    }

    public function substituteAsset(RentalService $rentalService): void
    {
        $this->authorize('operate', $this->rental);

        $data = $this->validate([
            'substitute_asset_id' => 'required|exists:assets,id',
            'substitute_motivo' => 'nullable|string|max:1000',
        ]);

        $newAsset = Asset::query()->findOrFail($data['substitute_asset_id']);

        try {
            $this->rental = $rentalService->substituteAsset(
                $this->rental,
                $newAsset,
                $data['substitute_motivo'] ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('substitute_asset_id', $e->getMessage());

            return;
        }

        $this->showSubstituteModal = false;
        $this->loadRental($this->rental);
        $this->syncFichaFields();
        session()->flash('success', 'Patrimônio substituído com sucesso. Histórico preservado na locação.');
    }

    public function openTransferCommercialModal(): void
    {
        $this->authorize('transferCommercialUser', $this->rental);
        $this->transfer_commercial_user_id = (string) ($this->rental->commercial_user_id ?? '');
        $this->showTransferCommercialModal = true;
    }

    public function transferCommercialUser(RentalService $rentalService): void
    {
        $this->authorize('transferCommercialUser', $this->rental);

        $data = $this->validate([
            'transfer_commercial_user_id' => 'required|exists:users,id',
        ]);

        $newUser = User::query()->whereKey($data['transfer_commercial_user_id'])->where('ativo', true)->firstOrFail();

        try {
            $this->rental = $rentalService->transferCommercialUser($this->rental, $newUser);
        } catch (\InvalidArgumentException $e) {
            $this->addError('transfer_commercial_user_id', $e->getMessage());

            return;
        }

        $this->showTransferCommercialModal = false;
        $this->loadRental($this->rental);
        session()->flash('success', 'Responsável comercial atualizado para '.$newUser->name.'.');
    }

    public function cancelReservation(RentalService $rentalService): void
    {
        $this->authorize('cancel', $this->rental);

        $this->validate([
            'cancelReason' => 'required|string|max:1000',
        ]);

        try {
            $this->rental = $rentalService->cancel($this->rental, $this->cancelReason);
        } catch (\InvalidArgumentException $e) {
            $this->addError('cancelReason', $e->getMessage());

            return;
        }

        $this->showCancelModal = false;
        $this->loadRental($this->rental);
        session()->flash('success', 'Reserva cancelada.');
    }

    public function render(): View
    {
        $pricingBreakdown = app(RentalPricingService::class)->calculateForRental($this->rental);

        return view('livewire.rental.rental-show', [
            'status' => $this->rental->statusEnum(),
            'saidaTemplate' => RentalService::CHECKLIST_SAIDA,
            'retornoTemplate' => RentalService::CHECKLIST_RETORNO,
            'fichaWarnings' => FichaCompleteness::rentalWarnings($this->rental),
            'fichaComplete' => FichaCompleteness::isRentalComplete($this->rental),
            'canEditCustomer' => $this->canEditCustomer(),
            'canEditAsset' => $this->canEditAsset(),
            'pricingBreakdown' => $pricingBreakdown,
            'pricingPeriodOptions' => RentalPricingPeriod::cases(),
            'commercialUsers' => User::query()->where('ativo', true)->orderBy('name')->get(['id', 'name']),
            'substituteAssetSuggestions' => $this->substituteAssetSuggestions(),
            'workflowSteps' => RentalWorkflow::steps($this->rental),
            'canOpenMaintenanceOrder' => RentalWorkflow::canOpenMaintenanceOrder($this->rental),
            'canGenerateReceivables' => RentalWorkflow::canGenerateReceivables($this->rental),
            'maintenanceTypeOptions' => MaintenanceOrderType::cases(),
            'pendingBillingCount' => app(BillingQueueReportQuery::class)->pendingCount(),
            'operatingCompanies' => \App\Models\Domain\Organization\OperatingCompany::query()->where('ativo', true)->orderBy('id')->get(),
            'highlightBillingEntry' => $this->highlightBillingEntryId
                ? RentalBillingQueueEntry::query()
                    ->with(['customer', 'rental', 'receivableTitle'])
                    ->find($this->highlightBillingEntryId)
                : null,
            'billingPayTitle' => $this->billingPayTitleId
                ? ReceivableTitle::find($this->billingPayTitleId)
                : null,
            'paymentMethods' => PaymentMethod::cases(),
            'billingInvoiceMethod' => 'invoiceBillingEntry',
            'billingShowQueueNav' => false,
        ]);
    }

    private function canEditCustomer(): bool
    {
        return auth()->user()->can('customers.manage');
    }

    private function canEditAsset(): bool
    {
        return auth()->user()->can('fleet.assets.manage')
            || auth()->user()->can('rentals.operate')
            || auth()->user()->can('rentals.reserve');
    }

    private function syncFichaFields(): void
    {
        $asset = $this->rental->asset;
        $customer = $this->rental->customer;

        $this->ficha_descricao = $this->rental->ficha_descricao ?? '';
        $this->ficha_horimetro_saida = $this->rental->horimetro_saida !== null ? (string) $this->rental->horimetro_saida : '';
        $this->ficha_horimetro_retorno = $this->rental->horimetro_retorno !== null ? (string) $this->rental->horimetro_retorno : '';
        $this->ficha_observacoes = $this->rental->observacoes ?? '';
        $this->ficha_valor_faturamento = $this->rental->valor_faturamento !== null ? (string) $this->rental->valor_faturamento : '';
        $this->ficha_valor_frete_entrega = $this->rental->valor_frete_entrega !== null ? (string) $this->rental->valor_frete_entrega : '';
        $this->ficha_valor_frete_recolhida = $this->rental->valor_frete_recolhida !== null ? (string) $this->rental->valor_frete_recolhida : '';
        $this->ficha_local_obra = $this->rental->local_obra ?? '';

        $this->asset_descricao = $asset->descricao ?? '';
        $this->asset_horimetro = $asset->horimetro !== null ? (string) $asset->horimetro : '';
        $this->asset_serie = $asset->serie ?? '';

        $this->customer_nome = $customer->nome;
        $this->customer_cpf_cnpj = $customer->formattedDocument();
        $this->customer_contato = $customer->contato ?? '';
        $this->customer_telefone = $customer->telefone ?? '';
        $this->customer_email = $customer->email ?? '';
        $this->customer_endereco = $customer->endereco ?? '';

        $this->billing_cycle_days = (string) ($this->rental->billing_cycle_days ?: 28);
        $this->billing_min_amount = $this->rental->billing_min_amount !== null ? (string) $this->rental->billing_min_amount : '';

        $this->item_indenizacao = $this->rental->items
            ->mapWithKeys(fn (RentalItem $item) => [
                $item->id => $item->valor_indenizacao !== null ? (string) $item->valor_indenizacao : '',
            ])
            ->all();
    }

    /** @return list<array{id: int, label: string, subtitle: string}> */
    private function substituteAssetSuggestions(): array
    {
        if (! $this->showSubstituteModal) {
            return [];
        }

        $term = trim($this->substitute_asset_search);

        return Asset::query()
            ->with('equipmentModel.category')
            ->where('status', AssetStatus::Disponivel->value)
            ->where('id', '!=', $this->rental->asset_id)
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('codigo_patrimonio', 'like', $like)
                        ->orWhereHas('equipmentModel', fn ($mq) => $mq
                            ->where('marca', 'like', $like)
                            ->orWhere('modelo', 'like', $like));
                });
            })
            ->orderBy('codigo_patrimonio')
            ->limit(12)
            ->get()
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'label' => $asset->codigo_patrimonio,
                'subtitle' => $asset->equipmentDisplayName().' — '.($asset->equipmentModel?->category?->nome ?? '—'),
            ])
            ->all();
    }

    private function resetChecklist(RentalChecklistType $tipo): void
    {
        $template = app(RentalService::class)->checklistTemplate($tipo);
        $this->checklistItems = array_fill_keys(array_keys($template), false);
        $this->checklistObservacoes = '';
        $this->resetValidation();
    }

    private function offerPostFlowNavigation(string $message, ?string $goUrl, ?string $goLabel = null): void
    {
        if ($goUrl === null) {
            session()->flash('success', $message);

            return;
        }

        $this->postFlowMessage = $message;
        $this->postFlowGoUrl = $goUrl;
        $this->postFlowGoLabel = $goLabel ?? 'Abrir';
        $this->showPostFlowPrompt = true;
    }

    private function resetPostFlowPrompt(): void
    {
        $this->showPostFlowPrompt = false;
        $this->postFlowMessage = '';
        $this->postFlowGoUrl = null;
        $this->postFlowGoLabel = 'Abrir';
    }

    private function applyCheckoutTitleDueDate(): void
    {
        if (blank($this->checkout_title_vencimento)) {
            return;
        }

        $entry = $this->rental->pendingBillingEntries()->with('receivableTitle')->first();

        if (! $entry?->receivableTitle) {
            return;
        }

        try {
            app(ReceivableTitleService::class)->updateOpenDueDate(
                $entry->receivableTitle,
                Carbon::parse($this->checkout_title_vencimento),
            );
            $this->loadRental($this->rental->fresh());
        } catch (\InvalidArgumentException) {
            // Mantém vencimento padrão se a data informada for inválida.
        }
    }

    private function syncBillingTitleVencimentos(): void
    {
        $this->billing_title_vencimento = $this->rental->billingQueueEntries
            ->filter(fn (RentalBillingQueueEntry $entry) => $entry->receivableTitle !== null
                && in_array($entry->statusEnum(), [RentalBillingQueueStatus::Pendente, RentalBillingQueueStatus::Autorizado], true))
            ->mapWithKeys(fn (RentalBillingQueueEntry $entry) => [
                $entry->id => $entry->receivableTitle->vencimento->toDateString(),
            ])
            ->all();
    }

    private function loadRental(Rental $rental): void
    {
        $this->rental = $rental->load([
            'operatingCompany',
            'asset.equipmentModel.category',
            'customer.createdByUser',
            'commercialUser',
            'reservedByUser',
            'checkoutByUser',
            'returnedByUser',
            'completedByUser',
            'cancelledByUser',
            'checklists.items',
            'checklists.user',
            'receivableTitles',
            'assetSubstitutions.fromAsset',
            'assetSubstitutions.toAsset',
            'assetSubstitutions.substitutedByUser',
            'maintenanceOrders.openedByUser',
            'attachments.user',
            'items.asset',
            'billingQueueEntries.receivableTitle',
            'billingQueueEntries.autorizadoPorUser',
            'billingQueueEntries.faturadoPorUser',
        ]);

        $this->syncBillingTitleVencimentos();
    }
}
