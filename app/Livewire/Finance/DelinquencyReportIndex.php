<?php

namespace App\Livewire\Finance;

use App\Enums\LateFeeRuleScope;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\LateFeeRule;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Rental\Rental;
use App\Services\LateFeeChargeService;
use App\Support\DelinquencyReportQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DelinquencyReportIndex extends Component
{
    use AuthorizesRequests;

    public string $search = '';

    public bool $showChargeModal = false;

    public string $rule_scope = 'global';

    public string $multa_percent = '2';

    public string $juros_mensal_percent = '1';

    public ?int $rule_customer_id = null;

    public string $rule_customer_search = '';

    /** @var array<int, array{id: int, nome: string, documento: string}> */
    public array $ruleCustomerSuggestions = [];

    public ?int $rule_rental_id = null;

    public string $rule_rental_search = '';

    /** @var array<int, array{id: int, codigo: string, customer_nome: string}> */
    public array $ruleRentalSuggestions = [];

    public string $batch_vencimento_from = '';

    public string $batch_vencimento_to = '';

    public string $batch_mode = 'use_saved_rules';

    public function mount(): void
    {
        $this->authorize('viewAny', ReceivableTitle::class);
    }

    public function openChargeModal(): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $global = LateFeeRule::query()->active()->global()->first();

        if ($global) {
            $this->multa_percent = (string) $global->multa_percent;
            $this->juros_mensal_percent = (string) $global->juros_mensal_percent;
        }

        $this->batch_vencimento_from = now()->subMonths(3)->format('Y-m-d');
        $this->batch_vencimento_to = now()->format('Y-m-d');
        $this->showChargeModal = true;
    }

    public function closeChargeModal(): void
    {
        $this->resetChargeForm();
        $this->showChargeModal = false;
    }

    public function updatedRuleCustomerSearch(): void
    {
        $this->searchRuleCustomers();
    }

    public function updatedRuleRentalSearch(): void
    {
        $this->searchRuleRentals();
    }

    public function selectRuleCustomer(int $customerId): void
    {
        $customer = Customer::query()->findOrFail($customerId);
        $this->rule_customer_id = $customer->id;
        $this->rule_customer_search = $customer->nome;
        $this->ruleCustomerSuggestions = [];

        $rule = LateFeeRule::query()->active()->forCustomer($customer->id)->first();
        if ($rule) {
            $this->multa_percent = (string) $rule->multa_percent;
            $this->juros_mensal_percent = (string) $rule->juros_mensal_percent;
        }
    }

    public function selectRuleRental(int $rentalId): void
    {
        $rental = Rental::query()->with('customer')->findOrFail($rentalId);
        $this->rule_rental_id = $rental->id;
        $this->rule_rental_search = $rental->codigo;
        $this->ruleRentalSuggestions = [];

        $rule = LateFeeRule::query()->active()->forRental($rental->id)->first();
        if ($rule) {
            $this->multa_percent = (string) $rule->multa_percent;
            $this->juros_mensal_percent = (string) $rule->juros_mensal_percent;
        }
    }

    public function saveLateFeeRule(): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $scope = LateFeeRuleScope::from($this->rule_scope);

        $rules = [
            'multa_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'juros_mensal_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];

        if ($scope === LateFeeRuleScope::Customer) {
            $rules['rule_customer_id'] = ['required', 'exists:customers,id'];
        }

        if ($scope === LateFeeRuleScope::Rental) {
            $rules['rule_rental_id'] = ['required', 'exists:rentals,id'];
        }

        $this->validate($rules, [
            'rule_customer_id.required' => 'Selecione um cliente.',
            'rule_rental_id.required' => 'Selecione uma locação.',
        ]);

        app(LateFeeChargeService::class)->saveRule(
            $scope,
            (float) $this->multa_percent,
            (float) $this->juros_mensal_percent,
            $this->rule_customer_id,
            $this->rule_rental_id,
        );

        session()->flash('success', 'Regra de multa e juros salva com sucesso.');
    }

    public function applyBatchCharges(): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $this->validate([
            'batch_vencimento_from' => ['required', 'date'],
            'batch_vencimento_to' => ['required', 'date', 'after_or_equal:batch_vencimento_from'],
            'batch_mode' => ['required', 'in:use_saved_rules,use_form_rates'],
        ], [
            'batch_vencimento_from.required' => 'Informe o início do período.',
            'batch_vencimento_to.required' => 'Informe o fim do período.',
        ]);

        if ($this->batch_mode === 'use_form_rates') {
            $this->validate([
                'multa_percent' => ['required', 'numeric', 'min:0', 'max:100'],
                'juros_mensal_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);
        }

        $from = Carbon::parse($this->batch_vencimento_from)->startOfDay();
        $to = Carbon::parse($this->batch_vencimento_to)->endOfDay();

        $applied = app(LateFeeChargeService::class)->applyBatch(
            $from,
            $to,
            $this->batch_mode === 'use_form_rates' ? (float) $this->multa_percent : null,
            $this->batch_mode === 'use_form_rates' ? (float) $this->juros_mensal_percent : null,
            $this->batch_mode === 'use_saved_rules',
        );

        session()->flash('success', "Encargos aplicados em {$applied->count()} título(s) no período informado.");
        $this->closeChargeModal();
    }

    public function render(): View
    {
        $query = app(DelinquencyReportQuery::class);

        return view('livewire.finance.delinquency-report-index', [
            'summary' => $query->summary(),
            'chargeSummary' => $query->chargeSummary($this->search),
            'customers' => $query->customersWithAging($this->search),
            'titleDetails' => $query->overdueTitlesWithCharges($this->search),
            'canManageCharges' => auth()->user()?->can('finance.manage') ?? false,
        ]);
    }

    private function resetChargeForm(): void
    {
        $this->rule_scope = 'global';
        $this->multa_percent = '2';
        $this->juros_mensal_percent = '1';
        $this->rule_customer_id = null;
        $this->rule_customer_search = '';
        $this->ruleCustomerSuggestions = [];
        $this->rule_rental_id = null;
        $this->rule_rental_search = '';
        $this->ruleRentalSuggestions = [];
        $this->batch_vencimento_from = '';
        $this->batch_vencimento_to = '';
        $this->batch_mode = 'use_saved_rules';
        $this->resetValidation();
    }

    private function searchRuleCustomers(): void
    {
        $term = trim($this->rule_customer_search);
        $this->rule_customer_id = null;
        $this->ruleCustomerSuggestions = [];

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $term);

        $this->ruleCustomerSuggestions = Customer::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $digits) {
                $query->where('nome', 'like', '%'.$term.'%');
                if ($digits !== '') {
                    $query->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome')
            ->limit(8)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'nome' => $customer->nome,
                'documento' => $customer->formattedDocument(),
            ])
            ->all();
    }

    private function searchRuleRentals(): void
    {
        $term = trim($this->rule_rental_search);
        $this->rule_rental_id = null;
        $this->ruleRentalSuggestions = [];

        if ($term === '') {
            return;
        }

        $this->ruleRentalSuggestions = Rental::query()
            ->with('customer')
            ->where('codigo', 'like', '%'.$term.'%')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Rental $rental) => [
                'id' => $rental->id,
                'codigo' => $rental->codigo,
                'customer_nome' => $rental->customer?->nome ?? '',
            ])
            ->all();
    }
}
