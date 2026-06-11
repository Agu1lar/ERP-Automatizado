<?php

namespace App\Http\Middleware;

use App\Models\Domain\Organization\OperatingCompany;
use App\Support\ActiveOperatingCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAgentOperatingCompany
{
  public function handle(Request $request, Closure $next): Response
  {
    $header = config('agent.operating_company_header', 'X-Operating-Company-Id');
    $companyId = $request->header($header);

    if (is_numeric($companyId)) {
      $company = OperatingCompany::query()
        ->whereKey((int) $companyId)
        ->where('ativo', true)
        ->first();

      if ($company) {
        ActiveOperatingCompany::set($company->id);
      }
    } elseif ($request->user()) {
      ActiveOperatingCompany::bootstrapForUser();
    }

    return $next($request);
  }
}
