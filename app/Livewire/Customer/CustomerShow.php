<?php

namespace App\Livewire\Customer;

use App\Enums\RentalStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Rules\ValidCpfCnpj;
use App\Support\RentalPanelQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CustomerShow extends Component
{
    use AuthorizesRequests, WithPagination;

    public Customer $customer;

    public string $nome = '';

    public string $cpf_cnpj = '';

    public string $contato = '';

    public string $telefone = '';

    public string $email = '';

    public string $endereco = '';

    public string $ativo = '1';

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->customer = $customer;
        $this->syncFields();
    }

    public function saveField(string $field): void
    {
        $this->authorize('update', $this->customer);

        match ($field) {
            'nome' => $this->customer->update([
                'nome' => $this->validateOnly('nome', ['nome' => 'required|string|max:255'])['nome'],
            ]),
            'cpf_cnpj' => $this->customer->update([
                'cpf_cnpj' => preg_replace(
                    '/\D/',
                    '',
                    $this->validateOnly('cpf_cnpj', [
                        'cpf_cnpj' => ['required', 'string', 'max:20', 'unique:customers,cpf_cnpj,'.$this->customer->id, new ValidCpfCnpj],
                    ])['cpf_cnpj'],
                ),
            ]),
            'contato' => $this->customer->update([
                'contato' => $this->validateOnly('contato', ['contato' => 'nullable|string|max:255'])['contato'] ?: null,
            ]),
            'telefone' => $this->customer->update([
                'telefone' => $this->validateOnly('telefone', ['telefone' => 'nullable|string|max:30'])['telefone'] ?: null,
            ]),
            'email' => $this->customer->update([
                'email' => $this->validateOnly('email', ['email' => 'nullable|email|max:255'])['email'] ?: null,
            ]),
            'endereco' => $this->customer->update([
                'endereco' => $this->validateOnly('endereco', ['endereco' => 'nullable|string'])['endereco'] ?: null,
            ]),
            'ativo' => $this->customer->update([
                'ativo' => $this->validateOnly('ativo', ['ativo' => 'required|in:0,1'])['ativo'] === '1',
            ]),
            default => abort(404),
        };

        $this->customer->refresh();
        $this->syncFields();
    }

    public function render(): View
    {
        $activeRentals = Rental::query()
            ->with(['asset.equipmentModel.category'])
            ->where('customer_id', $this->customer->id)
            ->active()
            ->orderBy('expected_return_at')
            ->get();

        $rentalHistory = Rental::query()
            ->with(['asset.equipmentModel.category'])
            ->where('customer_id', $this->customer->id)
            ->latest('reserved_at')
            ->paginate(15);

        $maintenanceOrders = MaintenanceOrder::query()
            ->with(['asset.equipmentModel'])
            ->where(function ($query) {
                $query->where('customer_id', $this->customer->id)
                    ->orWhereHas('rental', fn ($rentalQuery) => $rentalQuery->where('customer_id', $this->customer->id));
            })
            ->latest('opened_at')
            ->limit(10)
            ->get();

        $historySummary = app(RentalPanelQuery::class)->summaryForCustomer($this->customer->id);

        return view('livewire.customer.customer-show', [
            'activeRentals' => $activeRentals,
            'rentalHistory' => $rentalHistory,
            'maintenanceOrders' => $maintenanceOrders,
            'historySummary' => $historySummary,
            'statusOptions' => RentalStatus::cases(),
            'totalRevenue' => $this->customer->totalRevenue(),
            'canEdit' => auth()->user()->can('update', $this->customer),
        ]);
    }

    private function syncFields(): void
    {
        $this->nome = $this->customer->nome;
        $this->cpf_cnpj = $this->customer->formattedDocument();
        $this->contato = $this->customer->contato ?? '';
        $this->telefone = $this->customer->telefone ?? '';
        $this->email = $this->customer->email ?? '';
        $this->endereco = $this->customer->endereco ?? '';
        $this->ativo = $this->customer->ativo ? '1' : '0';
    }
}
