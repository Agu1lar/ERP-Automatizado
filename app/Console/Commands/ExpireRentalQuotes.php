<?php

namespace App\Console\Commands;

use App\Services\RentalQuoteService;
use Illuminate\Console\Command;

class ExpireRentalQuotes extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Expira orçamentos enviados após a data de validade';

    public function handle(RentalQuoteService $service): int
    {
        $count = $service->expireDueQuotes();

        $this->info("Orçamentos expirados: {$count}");

        return self::SUCCESS;
    }
}
