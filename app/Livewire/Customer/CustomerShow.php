<?php

namespace App\Livewire\Customer;

use App\Enums\CommercialActivityType;
use App\Enums\RentalStatus;
use App\Models\Domain\Crm\CommercialOpportunity;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Rules\ValidCpfCnpj;
use App\Services\CommercialActivityService;
use App\Services\ReceivableTitleService;
use App\Support\FlashMessage;
use App\Support\WhatsAppLinkBuilder;
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

    public string $limite_credito = '';

    public string $bloqueado = '0';

    public string $motivo_bloqueio = '';

    public string $activity_tipo = 'nota';

    public string $activity_descricao = '';

    public string $activity_follow_up = '';

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->customer = $customer->load(['createdByUser', 'blockedByUser']);
        $this->syncFields();
    }

    public function logActivity(CommercialActivityService $activities): void
    {
        $this->authorize('create', CommercialOpportunity::class);

        $data = $this->validate([
            'activity_tipo' => 'required|in:'.implode(',', array_column(CommercialActivityType::cases(), 'value')),
            'activity_descricao' => 'required|string|max:2000',
            'activity_follow_up' => 'nullable|date',
        ]);

        $activities->log(
            $this->customer,
            CommercialActivityType::from($data['activity_tipo']),
            $data['activity_descricao'],
            null,
            filled($data['activity_follow_up'] ?? null) ? \Carbon\Carbon::parse($data['activity_follow_up']) : null,
        );

        $this->activity_descricao = '';
        $this->activity_follow_up = '';
        $this->customer->refresh();

        FlashMessage::success('Atividade registrada.');
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
            'limite_credito' => $this->customer->update([
                'limite_credito' => ($v = $this->validateOnly('limite_credito', ['limite_credito' => 'nullable|numeric|min:0'])['limite_credito']) !== '' ? $v : null,
            ]),
            'bloqueado' => $this->updateManualBlock(),
            'motivo_bloqueio' => $this->updateBlockReason(),
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
        $financeService = app(ReceivableTitleService::class);

        $crmActivities = $this->customer->commercialActivities()->with('user')->limit(15)->get();
        $openOpportunities = $this->customer->commercialOpportunities()
            ->whereIn('stage', array_map(fn ($s) => $s->value, \App\Enums\OpportunityStage::pipelineStages()))
            ->get();

        return view('livewire.customer.customer-show', [
            'activeRentals' => $activeRentals,
            'rentalHistory' => $rentalHistory,
            'maintenanceOrders' => $maintenanceOrders,
            'historySummary' => $historySummary,
            'statusOptions' => RentalStatus::cases(),
            'totalRevenue' => $this->customer->totalRevenue(),
            'canEdit' => auth()->user()->can('update', $this->customer),
            'openBalance' => $financeService->customerOpenBalance($this->customer),
            'overdueBalance' => $financeService->customerOverdueBalance($this->customer),
            'canViewFinance' => auth()->user()->can('finance.view'),
            'canManageCrm' => auth()->user()->can('crm.manage'),
            'canViewCrm' => auth()->user()->can('crm.view'),
            'crmActivities' => $crmActivities,
            'openOpportunities' => $openOpportunities,
            'activityTypes' => CommercialActivityType::cases(),
            'whatsAppLink' => filled($this->customer->telefone)
                ? WhatsAppLinkBuilder::build($this->customer->telefone, 'Olá '.$this->customer->nome.', ')
                : null,
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
        $this->limite_credito = $this->customer->limite_credito !== null ? (string) $this->customer->limite_credito : '';
        $this->bloqueado = $this->customer->bloqueado ? '1' : '0';
        $this->motivo_bloqueio = $this->customer->motivo_bloqueio ?? '';
    }

    private function updateManualBlock(): void
    {
        $bloqueado = $this->validateOnly('bloqueado', ['bloqueado' => 'required|in:0,1'])['bloqueado'] === '1';

        $payload = ['bloqueado' => $bloqueado];

        if ($bloqueado) {
            $payload['motivo_bloqueio'] = trim($this->validateOnly('motivo_bloqueio', [
                'motivo_bloqueio' => 'required|string|max:2000',
            ])['motivo_bloqueio']);
        }

        Customer::applyManualBlockPayload($payload);
        $this->customer->update($payload);
    }

    private function updateBlockReason(): void
    {
        if ($this->bloqueado !== '1') {
            return;
        }

        $this->customer->update([
            'motivo_bloqueio' => trim($this->validateOnly('motivo_bloqueio', [
                'motivo_bloqueio' => 'required|string|max:2000',
            ])['motivo_bloqueio']),
        ]);
    }
}
