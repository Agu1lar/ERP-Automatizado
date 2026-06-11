<?php

namespace App\Http\Middleware;

use App\Support\ActiveOperatingCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveOperatingCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            ActiveOperatingCompany::bootstrapForUser();
        }

        return $next($request);
    }
}
