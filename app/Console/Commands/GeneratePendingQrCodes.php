<?php

namespace App\Console\Commands;

use App\Enums\QrCodeStatus;
use App\Jobs\GenerateAssetQrCodeJob;
use App\Models\Domain\Fleet\Asset;
use Illuminate\Console\Command;

class GeneratePendingQrCodes extends Command
{
    protected $signature = 'fleet:generate-qr-codes {--sync : Gera imediatamente sem usar a fila}';

    protected $description = 'Enfileira (ou gera) QR Codes para patrimônios pendentes ou com falha';

    public function handle(): int
    {
        $assets = Asset::query()
            ->whereIn('qr_code_status', [
                QrCodeStatus::Pending->value,
                QrCodeStatus::Failed->value,
            ])
            ->get();

        if ($assets->isEmpty()) {
            $this->info('Nenhum patrimônio pendente de QR Code.');

            return self::SUCCESS;
        }

        foreach ($assets as $asset) {
            if ($this->option('sync')) {
                GenerateAssetQrCodeJob::dispatchSync($asset->id);
                $this->line("QR gerado: {$asset->codigo_patrimonio}");
            } else {
                GenerateAssetQrCodeJob::dispatch($asset->id);
                $this->line("QR enfileirado: {$asset->codigo_patrimonio}");
            }
        }

        if (! $this->option('sync')) {
            $this->newLine();
            $this->info('Jobs enfileirados. Rode: php artisan queue:work');
        }

        return self::SUCCESS;
    }
}
