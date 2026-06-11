<?php

namespace App\Http\Controllers\Api\Agent;

use App\Agent\AgentCommandRegistry;
use App\Enums\AgentCommandSurface;
use App\Http\Controllers\Controller;
use App\Support\ActiveOperatingCompany;
use App\Support\Agent\AgentModeContext;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
  public function show(AgentCommandRegistry $registry): JsonResponse
  {
    $commands = $registry->manifest();

    $visualization = array_values(array_filter(
      $commands,
      fn (array $c) => ($c['surface'] ?? '') === AgentCommandSurface::Visualization->value,
    ));

    $execution = array_values(array_filter(
      $commands,
      fn (array $c) => ($c['surface'] ?? '') === AgentCommandSurface::Execution->value,
    ));

    return response()->json([
      'version' => '1.2',
      'system' => config('app.name'),
      'operating_company' => ActiveOperatingCompany::current()?->only(['id', 'nome', 'slug']),
      'philosophy' => 'API-first, sem visão computacional: visualização (consulta/navegação) separada de execução (mutação).',
      'modes' => AgentModeContext::forManifest(),
      'commands' => $commands,
      'commands_by_surface' => [
        'visualization' => $visualization,
        'execution' => $execution,
      ],
      'context_endpoints' => [
        'description' => 'APIs de visualização — leitura estruturada de fichas (nunca alteram dados).',
        'rental' => url('/api/agent/context/rental/{id_or_codigo}'),
        'customer' => url('/api/agent/context/customer/{id}'),
        'system' => url('/api/agent/context/system'),
        'maintenance' => url('/api/agent/context/maintenance/{id_or_codigo}'),
        'asset' => url('/api/agent/context/asset/{id_or_codigo}'),
        'quote' => url('/api/agent/context/quote/{id_or_codigo}'),
        'receivable' => url('/api/agent/context/receivable/{id_or_codigo}'),
      ],
      'command_endpoint' => url('/api/agent/commands/{command_name}'),
      'task_endpoints' => [
        'description' => 'APIs de execução em background (planos multi-passo, modo Agente).',
        'list' => url('/api/agent/tasks'),
        'create' => url('/api/agent/tasks'),
        'show' => url('/api/agent/tasks/{id}'),
        'cancel' => url('/api/agent/tasks/{id}/cancel'),
      ],
      'concurrency' => [
        'strategy' => 'optimistic_snapshot',
        'description' => 'Tarefas capturam updated_at dos recursos; alterações manuais no ERP invalidam a tarefa com status conflict.',
      ],
    ]);
  }
}
