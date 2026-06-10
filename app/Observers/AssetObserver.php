<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Jobs\GenerateAssetQrCodeJob;
use App\Models\Domain\Fleet\Asset;
use App\Services\AuditService;

class AssetObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function created(Asset $asset): void
    {
        $this->auditService->logModelChange($asset, AuditAction::Created, null, $this->auditPayload($asset));

        GenerateAssetQrCodeJob::dispatch($asset->id);
    }

    public function updated(Asset $asset): void
    {
        if ($asset->wasChanged(['status', 'localizacao'])) {
            return;
        }

        $this->auditService->logModelChange(
            $asset,
            AuditAction::Updated,
            $this->auditPayload($asset->getOriginal()),
            $this->auditPayload($asset),
        );
    }

    public function deleted(Asset $asset): void
    {
        $this->auditService->logModelChange($asset, AuditAction::Deleted, $this->auditPayload($asset));
    }

    private function auditPayload(Asset|array $asset): array
    {
        if (is_array($asset)) {
            return array_intersect_key($asset, array_flip([
                'codigo_patrimonio', 'valor_compra', 'status', 'localizacao', 'serie',
            ]));
        }

        return [
            'codigo_patrimonio' => $asset->codigo_patrimonio,
            'valor_compra' => $asset->valor_compra,
            'status' => $asset->status,
            'localizacao' => $asset->localizacao,
            'serie' => $asset->serie,
        ];
    }
}
