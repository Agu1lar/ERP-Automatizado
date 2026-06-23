<?php

namespace App\Console\Commands;

use App\Support\Agent\AgentManifestPayload;
use Illuminate\Console\Command;

class GenerateAgentManifest extends Command
{
  protected $signature = 'agent:manifest {--output= : Caminho do arquivo JSON (opcional)}';

  protected $description = 'Gera o manifest de capacidades do copiloto (comandos + schemas)';

  public function handle(AgentManifestPayload $manifest): int
  {
    $payload = array_merge($manifest->build(), [
      'generated_at' => now()->toIso8601String(),
      'auth' => [
        'type' => 'sanctum_bearer',
        'header_operating_company' => config('agent.operating_company_header'),
      ],
    ]);

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    if ($output = $this->option('output')) {
      file_put_contents($output, $json);
      $this->info("Manifest gravado em {$output}");

      return self::SUCCESS;
    }

    $this->line($json);

    return self::SUCCESS;
  }
}
