<?php

namespace App\Http\Controllers\Api\Agent;

use App\Agent\AgentContextBuilder;
use App\Http\Controllers\Controller;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalQuote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
  use AuthorizesRequests;

  public function rental(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $rental = $this->resolveRental($identifier);
    $this->authorize('view', $rental);

    return response()->json($builder->rental($rental));
  }

  public function customer(Customer $customer, AgentContextBuilder $builder): JsonResponse
  {
    $this->authorize('view', $customer);

    return response()->json($builder->customer($customer));
  }

  public function system(Request $request, AgentContextBuilder $builder): JsonResponse
  {
    $this->authorize('viewAny', \App\Models\Domain\Finance\ReceivableTitle::class);

    return response()->json($builder->systemSnapshot());
  }

  public function maintenance(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $order = $this->resolveMaintenanceOrder($identifier);
    $this->authorize('view', $order);

    return response()->json($builder->maintenanceOrder($order));
  }

  public function asset(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $asset = $this->resolveAsset($identifier);
    $this->authorize('view', $asset);

    return response()->json($builder->asset($asset));
  }

  public function quote(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $quote = $this->resolveQuote($identifier);
    $this->authorize('view', $quote);

    return response()->json($builder->quote($quote));
  }

  public function receivable(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $title = $this->resolveReceivableTitle($identifier);
    $this->authorize('view', $title);

    return response()->json($builder->receivableTitle($title));
  }

  private function resolveAsset(string $identifier): Asset
  {
    if (is_numeric($identifier)) {
      return Asset::query()->findOrFail((int) $identifier);
    }

    return Asset::query()->where('codigo_patrimonio', $identifier)->firstOrFail();
  }

  private function resolveQuote(string $identifier): RentalQuote
  {
    if (is_numeric($identifier)) {
      return RentalQuote::query()->findOrFail((int) $identifier);
    }

    return RentalQuote::query()->where('codigo', $identifier)->firstOrFail();
  }

  private function resolveReceivableTitle(string $identifier): ReceivableTitle
  {
    if (is_numeric($identifier)) {
      return ReceivableTitle::query()->findOrFail((int) $identifier);
    }

    return ReceivableTitle::query()->where('codigo', $identifier)->firstOrFail();
  }

  private function resolveMaintenanceOrder(string $identifier): MaintenanceOrder
  {
    if (is_numeric($identifier)) {
      return MaintenanceOrder::query()->findOrFail((int) $identifier);
    }

    return MaintenanceOrder::query()->where('codigo', $identifier)->firstOrFail();
  }

  private function resolveRental(string $identifier): Rental
  {
    if (is_numeric($identifier)) {
      return Rental::query()->findOrFail((int) $identifier);
    }

    return Rental::query()->where('codigo', $identifier)->firstOrFail();
  }
}
