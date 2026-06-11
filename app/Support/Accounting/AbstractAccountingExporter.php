<?php

namespace App\Support\Accounting;

use App\Models\Domain\Finance\ReceivableTitle;
use Illuminate\Support\Collection;

abstract class AbstractAccountingExporter implements AccountingExportFormatter
{
    /** @param  Collection<int, ReceivableTitle>  $titles @param  resource  $handle */
    public function write(Collection $titles, $handle): void
    {
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, $this->headers(), ';');

        foreach ($titles as $title) {
            fputcsv($handle, $this->mapRow($title), ';');
        }
    }

    protected function money(float|string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    protected function dateBr(?\DateTimeInterface $date): string
    {
        return $date?->format('d/m/Y') ?? '';
    }

    protected function onlyDigits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
