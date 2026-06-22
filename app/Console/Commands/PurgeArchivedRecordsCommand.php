<?php

namespace App\Console\Commands;

use App\Services\ArchiveService;
use Illuminate\Console\Command;

class PurgeArchivedRecordsCommand extends Command
{
    protected $signature = 'archive:purge {--dry-run : Apenas listar o que seria removido}';

    protected $description = 'Remove definitivamente registros arquivados após o período de retenção configurado';

    public function handle(ArchiveService $archiveService): int
    {
        $days = $archiveService->retentionDays();
        $before = now()->subDays($days);

        if ($this->option('dry-run')) {
            $this->info("Simulação — registros com deleted_at <= {$before->toDateTimeString()}");
            $total = 0;

            foreach (config('archive.models', []) as $class) {
                if (! class_exists($class) || ! method_exists($class, 'onlyTrashed')) {
                    continue;
                }

                $count = $class::onlyTrashed()->where('deleted_at', '<=', $before)->count();

                if ($count > 0) {
                    $this->line("  {$class}: {$count}");
                    $total += $count;
                }
            }

            $this->info("Total: {$total}");

            return self::SUCCESS;
        }

        $result = $archiveService->purgeExpired($before);

        foreach ($result['by_class'] as $class => $count) {
            $this->line("  {$class}: {$count}");
        }

        $this->info("Exclusão definitiva concluída — {$result['total']} registro(s).");

        return self::SUCCESS;
    }
}
