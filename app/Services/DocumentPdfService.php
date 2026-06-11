<?php

namespace App\Services;

use App\Enums\CustomFieldEntity;
use App\Enums\DocumentType;
use App\Models\Domain\Fleet\Asset;
use App\Support\BrandContext;
use App\Support\FichaCompleteness;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\Storage;

class DocumentPdfService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
        private readonly CustomFieldService $customFieldService,
    ) {}

    public function maintenanceOrder(MaintenanceOrder $order): PDF
    {
        $order->load([
            'asset.equipmentModel.category',
            'customer',
            'rental.customer',
            'openedByUser',
            'assignedToUser',
            'completedByUser',
            'parts',
            'laborHours.user',
        ]);

        return $this->render(
            DocumentType::MaintenanceOrder,
            [
                'order' => $order,
                'generatedAt' => now(),
                'customFieldRows' => $this->customFieldService->displayRows(
                    CustomFieldEntity::MaintenanceOrder,
                    $order->id,
                ),
            ],
            "{$order->codigo}.pdf",
        );
    }

    public function rentalSummary(Rental $rental): PDF
    {
        $rental->load([
            'asset.equipmentModel.category',
            'customer.createdByUser',
            'commercialUser',
            'reservedByUser',
            'checkoutByUser',
            'returnedByUser',
            'completedByUser',
            'checklists.items',
            'checklists.user',
        ]);

        return $this->render(
            DocumentType::RentalSummary,
            [
                'rental' => $rental,
                'generatedAt' => now(),
                'fichaWarnings' => FichaCompleteness::rentalWarnings($rental),
                'fichaComplete' => FichaCompleteness::isRentalComplete($rental),
                'customFieldRows' => $this->customFieldService->displayRows(
                    CustomFieldEntity::Rental,
                    $rental->id,
                ),
            ],
            "{$rental->codigo}.pdf",
        );
    }

    public function rentalContract(Rental $rental): PDF
    {
        $rental->load([
            'asset.equipmentModel.category',
            'customer',
            'commercialUser',
            'checkoutByUser',
            'assetSubstitutions.fromAsset',
            'assetSubstitutions.toAsset',
        ]);

        return $this->render(
            DocumentType::RentalContract,
            [
                'rental' => $rental,
                'generatedAt' => now(),
                'clauses' => config('documents.rental_contract_clauses', []),
            ],
            "{$rental->codigo}-contrato.pdf",
        );
    }

    public function billingInvoice(RentalBillingQueueEntry $entry): PDF
    {
        $entry->load([
            'customer',
            'rental.asset.equipmentModel.category',
            'rental.operatingCompany',
            'receivableTitle',
            'operatingCompany',
        ]);

        return $this->render(
            DocumentType::BillingInvoice,
            [
                'entry' => $entry,
                'generatedAt' => now(),
            ],
            "fatura-{$entry->codigo}.pdf",
        );
    }

    public function assetSheet(Asset $asset): PDF
    {
        $asset->load(['equipmentModel.category']);

        return $this->render(
            DocumentType::AssetSheet,
            [
                'asset' => $asset,
                'qrBase64' => $this->qrCodeService->base64DataUri($asset),
                'generatedAt' => now(),
                'fichaWarnings' => FichaCompleteness::assetWarnings($asset),
                'fichaComplete' => FichaCompleteness::isAssetComplete($asset),
                'customFieldRows' => $this->customFieldService->displayRows(
                    CustomFieldEntity::Asset,
                    $asset->id,
                ),
            ],
            "ficha-{$asset->codigo_patrimonio}.pdf",
        );
    }

    /** @param array<string, mixed> $data */
    private function render(DocumentType $type, array $data, string $filename): PDF
    {
        $company = $this->resolveCompanyData($data);

        $viewData = array_merge($data, [
            'company' => $company,
            'documentTitle' => $type->label(),
            'logoBase64' => $this->logoBase64($company['logo_path'] ?? null),
        ]);

        $pdf = app('dompdf.wrapper')
            ->loadView($type->template(), $viewData)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans');

        $paper = config("documents.paper.{$type->value}", [
            'format' => 'a4',
            'orientation' => 'portrait',
        ]);

        if (isset($paper['width_mm'], $paper['height_mm'])) {
            $pdf->setPaper([
                0,
                0,
                $this->mmToPoints((float) $paper['width_mm']),
                $this->mmToPoints((float) $paper['height_mm']),
            ]);
        } else {
            $pdf->setPaper($paper['format'] ?? 'a4', $paper['orientation'] ?? 'portrait');
        }

        return $pdf;
    }

    private function mmToPoints(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    /** @param array<string, mixed> $data */
    /** @return array<string, string|null> */
    private function resolveCompanyData(array $data): array
    {
        foreach (['entry', 'rental', 'order', 'asset'] as $key) {
            if (! isset($data[$key]) || ! is_object($data[$key])) {
                continue;
            }

            $model = $data[$key];

            if (! method_exists($model, 'operatingCompany')) {
                continue;
            }

            $model->loadMissing('operatingCompany');

            if ($model->operatingCompany) {
                return $model->operatingCompany->documentHeader();
            }
        }

        return BrandContext::documentHeader();
    }

    private function logoBase64(?string $path = null): ?string
    {
        $path ??= config('documents.company.logo_path');

        if (blank($path)) {
            return null;
        }

        $candidates = [
            public_path($path),
            base_path($path),
        ];

        $absolute = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $absolute = $candidate;
                break;
            }
        }

        if ($absolute === null && Storage::disk('local')->exists($path)) {
            $absolute = Storage::disk('local')->path($path);
        }

        if ($absolute === null) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute));
    }
}
