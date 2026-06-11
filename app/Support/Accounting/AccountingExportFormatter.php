<?php

namespace App\Support\Accounting;

use App\Models\Domain\Finance\ReceivableTitle;
use Illuminate\Support\Collection;

interface AccountingExportFormatter
{
    /** @return list<string> */
    public function headers(): array;

    /** @return list<string|int|float|null> */
    public function mapRow(ReceivableTitle $title): array;

    public function filename(): string;

    /** @param  Collection<int, ReceivableTitle>  $titles @param  resource  $handle */
    public function write(Collection $titles, $handle): void;
}
