<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Support\Agent\AgentManifestPayload;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
  public function show(AgentManifestPayload $manifest): JsonResponse
  {
    return response()->json($manifest->build());
  }
}
