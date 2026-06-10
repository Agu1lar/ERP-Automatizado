<?php

namespace App\Livewire\Rental;

use App\Enums\RentalChecklistType;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;
use App\Rules\ValidCpfCnpj;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use App\Support\FichaCompleteness;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RentalShow extends Component
{
    use AuthorizesRequests;

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

    public string $asset_descricao = '';

    public string $asset_horimetro = '';

    public string $asset_serie = '';

    public string $customer_nome = '';

    public string $customer_cpf_cnpj = '';

    public string $customer_contato = '';

    public string $customer_telefone = '';

    public string $customer_email = '';

    public string $customer_endereco = '';

    public function mount(Rental $rental): void
    {
        $this->authorize('view', $rental);
        $this->loadRental($rental);
        $this->syncFichaFields();
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
        ]);

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
            'ficha_valor_faturamento' => $this->rental->update([
                'valor_faturamento' => ($v = $this->validateOnly('ficha_valor_faturamento', ['ficha_valor_faturamento' => 'nullable|numeric|min:0'])['ficha_valor_faturamento']) !== '' ? $v : null,
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

    public function openCheckoutModal(): void
    {
        $this->authorize('operate', $this->rental);
        $this->resetChecklist(RentalChecklistType::Saida);
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

    public function checkout(RentalService $rentalService): void
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
        session()->flash('success', 'Saída registrada com sucesso.');
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
        session()->flash('success', 'Retorno registrado com sucesso.');
    }

    public function completeInspection(RentalService $rentalService): void
    {
        $this->authorize('operate', $this->rental);

        $this->validate([
            'motivoManutencao' => $this->sendToMaintenance ? 'required|string|max:1000' : 'nullable',
        ]);

        try {
            $this->rental = $rentalService->completeInspection(
                $this->rental,
                $this->sendToMaintenance,
                $this->motivoManutencao ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('complete', $e->getMessage());

            return;
        }

        $this->showCompleteModal = false;
        $this->loadRental($this->rental);
        session()->flash('success', 'Inspeção concluída. Locação finalizada.');
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
    }

    private function resetChecklist(RentalChecklistType $tipo): void
    {
        $template = app(RentalService::class)->checklistTemplate($tipo);
        $this->checklistItems = array_fill_keys(array_keys($template), false);
        $this->checklistObservacoes = '';
        $this->resetValidation();
    }

    private function loadRental(Rental $rental): void
    {
        $this->rental = $rental->load([
            'asset.equipmentModel.category',
            'customer',
            'reservedByUser',
            'checkoutByUser',
            'returnedByUser',
            'completedByUser',
            'cancelledByUser',
            'checklists.items',
            'checklists.user',
        ]);
    }
}
