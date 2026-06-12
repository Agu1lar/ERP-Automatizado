<?php

namespace App\Console\Commands;

use App\Services\OutboundMessagingService;
use Illuminate\Console\Command;

class ProcessOutboundMessages extends Command
{
    protected $signature = 'crm:process-outbound {--limit=50}';

    protected $description = 'Processa fila de mensagens WhatsApp/SMS pendentes';

    public function handle(OutboundMessagingService $service): int
    {
        $count = $service->processPending((int) $this->option('limit'));
        $this->info("Processadas {$count} mensagem(ns).");

        return self::SUCCESS;
    }
}
