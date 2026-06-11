<?php

namespace App\Support\Accounting;

use InvalidArgumentException;

class AccountingExportRegistry
{
    /** @var array<string, class-string<AccountingExportFormatter>> */
    private array $formatters = [
        'csv' => GenericAccountingExporter::class,
        'omie' => OmieAccountingExporter::class,
        'bling' => BlingAccountingExporter::class,
        'sisloc' => SislocAccountingExporter::class,
    ];

    public function get(string $format): AccountingExportFormatter
    {
        $format = strtolower($format);

        if (! isset($this->formatters[$format])) {
            throw new InvalidArgumentException("Formato contábil desconhecido: {$format}");
        }

        return app($this->formatters[$format]);
    }

    /** @return array<string, array{label: string, description: string}> */
    public function formats(): array
    {
        return config('accounting.formats', []);
    }
}
