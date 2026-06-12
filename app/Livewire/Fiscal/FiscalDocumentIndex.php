<?php

namespace App\Livewire\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Models\Domain\Fiscal\FiscalDocument;
use App\Services\FiscalBridgeService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class FiscalDocumentIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', FiscalDocument::class);
    }

    public function pushToOmie(FiscalBridgeService $bridge): void
    {
        $this->authorize('create', FiscalDocument::class);

        $results = $bridge->pushPendingToOmie(auth()->user());
        $count = $results->count();

        session()->flash('success', $count > 0
            ? "{$count} documento(s) fiscal(is) processado(s) para o Omie."
            : 'Nenhum documento pendente para envio.');
    }

    public function markEmitted(int $id): void
    {
        $document = FiscalDocument::query()->findOrFail($id);
        $this->authorize('update', $document);

        app(FiscalBridgeService::class)->markEmitted($document);
        session()->flash('success', "Documento {$document->codigo} marcado como emitido.");
    }

    public function render(): View
    {
        $documents = FiscalDocument::query()
            ->with(['rental', 'receivableTitle.customer'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.fiscal.fiscal-document-index', [
            'documents' => $documents,
            'statusOptions' => FiscalDocumentStatus::cases(),
        ]);
    }
}
