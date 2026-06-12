<?php

namespace App\Livewire\Crm;

use App\Enums\OpportunityStage;
use App\Models\Domain\Crm\CommercialOpportunity;
use App\Models\Domain\Customer\Customer;
use App\Services\CommercialOpportunityService;
use App\Support\FlashMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class CommercialPipelineIndex extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $search = '';

    public bool $showLeadForm = false;

    public ?int $customer_id = null;

    public string $titulo = '';

    public string $descricao = '';

    public string $valor_estimado = '';

    public string $proximo_follow_up_em = '';

    public string $customer_search = '';

    public ?int $lostOpportunityId = null;

    public string $lost_reason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CommercialOpportunity::class);
    }

    public function openLeadForm(): void
    {
        $this->authorize('create', CommercialOpportunity::class);
        $this->resetLeadForm();
        $this->showLeadForm = true;
    }

    public function saveLead(CommercialOpportunityService $service): void
    {
        $this->authorize('create', CommercialOpportunity::class);

        $data = $this->validate([
            'customer_id' => 'required|exists:customers,id',
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:2000',
            'valor_estimado' => 'nullable|numeric|min:0',
            'proximo_follow_up_em' => 'nullable|date',
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);

        $service->createLead(
            $customer,
            $data['titulo'],
            $data['descricao'] ?? null,
            isset($data['valor_estimado']) && $data['valor_estimado'] !== '' ? (float) $data['valor_estimado'] : null,
            null,
            filled($data['proximo_follow_up_em'] ?? null) ? \Carbon\Carbon::parse($data['proximo_follow_up_em']) : null,
        );

        $this->showLeadForm = false;
        FlashMessage::success('Lead criado no pipeline.');
    }

    public function advanceStage(int $opportunityId, string $stage, CommercialOpportunityService $service): void
    {
        $opportunity = CommercialOpportunity::query()->findOrFail($opportunityId);
        $this->authorize('update', $opportunity);

        $target = OpportunityStage::from($stage);
        $service->moveStage($opportunity, $target);

        FlashMessage::success('Estágio atualizado.');
    }

    public function openLostModal(int $opportunityId): void
    {
        $opportunity = CommercialOpportunity::query()->findOrFail($opportunityId);
        $this->authorize('update', $opportunity);
        $this->lostOpportunityId = $opportunityId;
        $this->lost_reason = '';
    }

    public function markLost(CommercialOpportunityService $service): void
    {
        $opportunity = CommercialOpportunity::query()->findOrFail($this->lostOpportunityId);
        $this->authorize('update', $opportunity);

        $this->validate(['lost_reason' => 'required|string|max:500']);

        $service->moveStage($opportunity, OpportunityStage::Perdido, $this->lost_reason);
        $this->lostOpportunityId = null;
        FlashMessage::success('Oportunidade marcada como perdida.');
    }

    public function render(CommercialOpportunityService $service): View
    {
        $pipeline = $service->pipelineGrouped();

        if (filled($this->search)) {
            $term = mb_strtolower(trim($this->search));
            $pipeline = $pipeline->map(fn ($items) => $items->filter(function (CommercialOpportunity $opp) use ($term) {
                return str_contains(mb_strtolower($opp->titulo), $term)
                    || str_contains(mb_strtolower($opp->customer?->nome ?? ''), $term);
            })->values());
        }

        $customerOptions = collect();

        if ($this->showLeadForm && strlen($this->customer_search) >= 2) {
            $term = '%'.mb_strtolower(trim($this->customer_search)).'%';
            $customerOptions = Customer::query()
                ->where('ativo', true)
                ->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(nome) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(telefone) LIKE ?', [$term]);
                })
                ->orderBy('nome')
                ->limit(15)
                ->get();
        }

        $dueCount = $service->dueFollowUpsQuery()->count();

        return view('livewire.crm.commercial-pipeline-index', [
            'pipeline' => $pipeline,
            'stages' => OpportunityStage::pipelineStages(),
            'customerOptions' => $customerOptions,
            'canManage' => auth()->user()->can('crm.manage'),
            'dueCount' => $dueCount,
        ]);
    }

    private function resetLeadForm(): void
    {
        $this->customer_id = null;
        $this->titulo = '';
        $this->descricao = '';
        $this->valor_estimado = '';
        $this->proximo_follow_up_em = '';
        $this->customer_search = '';
    }
}
