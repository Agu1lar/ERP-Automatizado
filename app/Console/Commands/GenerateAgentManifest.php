<?php

namespace App\Console\Commands;

use App\Agent\AgentCommandRegistry;
use Illuminate\Console\Command;

class GenerateAgentManifest extends Command
{
  protected $signature = 'agent:manifest {--output= : Caminho do arquivo JSON (opcional)}';

  protected $description = 'Gera o manifest de capacidades do copiloto (comandos + schemas)';

  public function handle(AgentCommandRegistry $registry): int
  {
    $payload = [
      'generated_at' => now()->toIso8601String(),
      'version' => '1.0',
      'system' => config('app.name'),
      'commands' => $registry->manifest(),
      'context_endpoints' => [
        'rental' => '/api/agent/context/rental/{id_or_codigo}',
        'customer' => '/api/agent/context/customer/{id}',
        'system' => '/api/agent/context/system',
      ],
      'auth' => [
        'type' => 'sanctum_bearer',
        'header_operating_company' => config('agent.operating_company_header'),
      ],
    ];

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
