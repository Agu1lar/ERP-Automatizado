<?php

namespace App\Livewire\Logistics;

use App\Models\Domain\Logistics\DeliveryManifest;
use App\Models\Domain\Logistics\DeliveryManifestStop;
use App\Services\DeliveryManifestService;
use App\Support\FlashMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.mobile-yard')]
class ManifestStopProof extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public DeliveryManifest $manifest;

    public DeliveryManifestStop $stop;

    public string $receptor_nome = '';

    public string $assinatura_imagem = '';

    public $foto;

    public string $observacoes = '';

    public function mount(DeliveryManifest $manifest, DeliveryManifestStop $stop): void
    {
        $this->authorize('update', $manifest);

        if ($stop->delivery_manifest_id !== $manifest->id) {
            abort(404);
        }

        $this->manifest = $manifest;
        $this->stop = $stop->load(['rental.customer', 'rental.asset', 'proof']);
    }

    public function submit(DeliveryManifestService $service): void
    {
        $this->authorize('update', $this->manifest);

        $this->validate([
            'receptor_nome' => 'required|string|max:255',
            'foto' => 'nullable|image|max:5120',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $service->recordProof(
                $this->stop,
                $this->receptor_nome,
                filled($this->assinatura_imagem) ? $this->assinatura_imagem : null,
                $this->foto,
                $this->observacoes ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            FlashMessage::error($e->getMessage());

            return;
        }

        FlashMessage::success('Comprovante registrado.');
        $this->redirectRoute('logistics.manifest.show', $this->manifest, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.logistics.manifest-stop-proof');
    }
}
