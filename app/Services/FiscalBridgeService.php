<?php

namespace App\Services;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\RentalBillingQueueType;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fiscal\FiscalDocument;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
class FiscalBridgeService
{
    public function registerFromInvoice(
        RentalBillingQueueEntry $entry,
        ReceivableTitle $title,
    ): ?FiscalDocument {
        if (! config('fiscal.enabled')) {
            return null;
        }

        if (FiscalDocument::query()->where('billing_queue_entry_id', $entry->id)->exists()) {
            return null;
        }

        $tipo = $this->resolveDocumentType($entry);
        $descricao = $entry->observacoes ?: $title->observacoes ?: "Faturamento {$entry->codigo}";

        return FiscalDocument::create([
            'operating_company_id' => $title->operating_company_id,
            'rental_id' => $entry->rental_id,
            'receivable_title_id' => $title->id,
            'billing_queue_entry_id' => $entry->id,
            'codigo' => $this->generateCodigo(),
            'tipo' => $tipo->value,
            'status' => FiscalDocumentStatus::Pendente->value,
            'valor' => $entry->valor_nf,
            'descricao' => $descricao,
            'erp_provider' => config('fiscal.default_erp', 'omie'),
            'erp_payload' => $this->buildOmiePayload($entry, $title, $tipo),
        ]);
    }

    public function markSentToErp(FiscalDocument $document, ?User $user = null, ?string $externalId = null): FiscalDocument
    {
        $document->update([
            'status' => FiscalDocumentStatus::EnviadoErp->value,
            'enviado_erp_em' => now(),
            'enviado_erp_por' => $user?->id,
            'erp_external_id' => $externalId,
            'erro_mensagem' => null,
        ]);

        return $document->fresh();
    }

    public function markEmitted(FiscalDocument $document, ?string $externalId = null): FiscalDocument
    {
        $document->update([
            'status' => FiscalDocumentStatus::Emitido->value,
            'emitido_em' => now(),
            'erp_external_id' => $externalId ?? $document->erp_external_id,
            'erro_mensagem' => null,
        ]);

        return $document->fresh();
    }

    public function markError(FiscalDocument $document, string $message): FiscalDocument
    {
        $document->update([
            'status' => FiscalDocumentStatus::Erro->value,
            'erro_mensagem' => $message,
        ]);

        return $document->fresh();
    }

    /** @return Collection<int, FiscalDocument> */
    public function pushPendingToOmie(?User $user = null): Collection
    {
        $appKey = config('fiscal.omie.app_key');
        $appSecret = config('fiscal.omie.app_secret');

        $pending = FiscalDocument::query()
            ->pending()
            ->where('erp_provider', 'omie')
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($pending->isEmpty()) {
            return collect();
        }

        if (! $appKey || ! $appSecret) {
            return $pending->map(function (FiscalDocument $doc) use ($user) {
                return $this->markSentToErp($doc, $user, 'manual-'.$doc->codigo);
            });
        }

        return $pending->map(function (FiscalDocument $doc) use ($user, $appKey, $appSecret) {
            try {
                $response = Http::timeout(30)->post('https://app.omie.com.br/api/v1/servicos/os/', [
                    'call' => 'IncluirOS',
                    'app_key' => $appKey,
                    'app_secret' => $appSecret,
                    'param' => [$doc->erp_payload],
                ]);

                if ($response->failed()) {
                    return $this->markError($doc, 'Omie HTTP '.$response->status().': '.$response->body());
                }

                $body = $response->json();
                if (! empty($body['faultstring'])) {
                    return $this->markError($doc, (string) $body['faultstring']);
                }

                $externalId = (string) ($body['nCodOS'] ?? $body['codigo_os'] ?? $doc->codigo);

                return $this->markSentToErp($doc, $user, $externalId);
            } catch (\Throwable $e) {
                return $this->markError($doc, $e->getMessage());
            }
        });
    }

    private function resolveDocumentType(RentalBillingQueueEntry $entry): FiscalDocumentType
    {
        $tipo = $entry->tipoEnum();

        return match ($tipo) {
            RentalBillingQueueType::FreteEntrega,
            RentalBillingQueueType::FreteRecolhida,
            RentalBillingQueueType::Locacao,
            RentalBillingQueueType::Renovacao,
            RentalBillingQueueType::Indenizacao => FiscalDocumentType::Nfse,
        };
    }

  /** @return array<string, mixed> */
    private function buildOmiePayload(
        RentalBillingQueueEntry $entry,
        ReceivableTitle $title,
        FiscalDocumentType $tipo,
    ): array {
        $title->loadMissing('customer');
        $customer = $title->customer;

        return [
            'cabecalho' => [
                'cCodIntOS' => $entry->codigo,
                'cEtapa' => '10',
                'dDtPrevisao' => $title->vencimento->format('d/m/Y'),
                'nCodCli' => $customer->cpf_cnpj,
            ],
            'servicos' => [[
                'cDescServ' => $tipo->label().' — '.$entry->tipoEnum()->label(),
                'nValUnit' => round((float) $entry->valor_nf, 2),
                'nQtde' => 1,
                'cCodServLC116' => config('fiscal.omie.nfse_service_code'),
            ]],
            'informacoes_adicionais' => [
                'cCidPrestServ' => 'São Paulo',
                'cDadosAdicNF' => $entry->observacoes,
            ],
        ];
    }

    private function generateCodigo(): string
    {
        $prefix = 'FIS-'.now()->format('ym');
        $last = FiscalDocument::query()
            ->where('codigo', 'like', $prefix.'%')
            ->orderByDesc('codigo')
            ->value('codigo');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
