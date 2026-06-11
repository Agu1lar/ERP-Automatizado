<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentApiAccess
{
  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();

    if (! $user || ! $user->isActive()) {
      return response()->json(['message' => 'Não autenticado.'], 401);
    }

    $permission = config('agent.access_permission', 'agent.api');

    if (! $user->can($permission)) {
      return response()->json(['message' => 'Sem permissão para API do agente.'], 403);
    }

    return $next($request);
  }
}
