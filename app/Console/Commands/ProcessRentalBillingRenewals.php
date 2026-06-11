<?php

namespace App\Console\Commands;

use App\Services\RentalBillingService;
use App\Support\ActiveOperatingCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ProcessRentalBillingRenewals extends Command
{
    protected $signature = 'rentals:process-billing-renewals {--dry-run : Apenas listar locações vencidas, sem criar fila}';

    protected $description = 'Gera entradas na fila a faturar para locações com ciclo de faturamento vencido';

    public function handle(RentalBillingService $billingService): int
    {
        if ($this->option('dry-run')) {
            $count = collect(ActiveOperatingCompany::forEach(
                fn () => \App\Models\Domain\Rental\Rental::query()
                    ->where('status', \App\Enums\RentalStatus::Locado->value)
                    ->whereNotNull('next_billing_at')
                    ->whereDate('next_billing_at', '<=', now()->toDateString())
                    ->count()
            ))->sum();

            $this->info("{$count} locação(ões) com renovação de faturamento vencida.");

            return self::SUCCESS;
        }

        $created = collect(ActiveOperatingCompany::forEach(
            fn () => $billingService->processDueRenewals()
        ))->flatten(1);

        $this->info("{$created->count()} renovação(ões) incluída(s) na fila a faturar.");

        return self::SUCCESS;
    }
}
