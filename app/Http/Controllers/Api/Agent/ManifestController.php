<?php

namespace App\Http\Controllers\Api\Agent;

use App\Agent\AgentCommandRegistry;
use App\Http\Controllers\Controller;
use App\Support\ActiveOperatingCompany;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
  public function show(AgentCommandRegistry $registry): JsonResponse
  {
    return response()->json([
      'version' => '1.0',
      'system' => config('app.name'),
      'operating_company' => ActiveOperatingCompany::current()?->only(['id', 'nome', 'slug']),
      'commands' => $registry->manifest(),
      'context_endpoints' => [
        'rental' => url('/api/agent/context/rental/{id_or_codigo}'),
        'customer' => url('/api/agent/context/customer/{id}'),
        'system' => url('/api/agent/context/system'),
      ],
      'command_endpoint' => url('/api/agent/commands/{command_name}'),
    ]);
  }
}
