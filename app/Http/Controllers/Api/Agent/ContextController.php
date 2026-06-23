<?php

namespace App\Http\Controllers\Api\Agent;

use App\Agent\AgentContextBuilder;
use App\Http\Controllers\Controller;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\Domain\Rental\RentalQuote;
use Carbon\Carbon;
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

  public function person(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $person = $this->resolvePerson($identifier);
    $this->authorize('view', $person);

    return response()->json($builder->person($person));
  }

  public function company(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $company = $this->resolveCompany($identifier);
    $this->authorize('view', $company);

    return response()->json($builder->company($company));
  }

  public function billing(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $entry = $this->resolveBillingEntry($identifier);
    $this->authorize('viewAny', ReceivableTitle::class);

    return response()->json($builder->billingEntry($entry));
  }

  public function yard(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    $yard = $this->resolveYard($identifier);
    abort_unless(auth()->user()?->can('fleet.assets.view'), 403);

    return response()->json($builder->yard($yard));
  }

  public function logistics(Request $request, AgentContextBuilder $builder): JsonResponse
  {
    abort_unless(auth()->user()?->can('rentals.view'), 403);

    $date = $request->query('date')
      ? Carbon::parse((string) $request->query('date'))->startOfDay()
      : null;

    return response()->json($builder->logisticsDaily($date));
  }

  public function knowledge(AgentContextBuilder $builder): JsonResponse
  {
    abort_unless(auth()->user()?->can('agent.api'), 403);

    return response()->json($builder->knowledge());
  }

  public function pricing(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    abort_unless(auth()->user()?->can('pricing.view'), 403);

    $category = $this->resolveCategory($identifier);

    return response()->json($builder->pricingCategory($category));
  }

  public function part(string $identifier, AgentContextBuilder $builder): JsonResponse
  {
    abort_unless(auth()->user()?->can('maintenance.view'), 403);

    $part = $this->resolvePart($identifier);

    return response()->json($builder->partCatalogItem($part));
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

  private function resolvePerson(string $identifier): Person
  {
    if (is_numeric($identifier)) {
      return Person::query()->findOrFail((int) $identifier);
    }

    $digits = preg_replace('/\D/', '', $identifier);

    if ($digits !== '') {
      return Person::query()->where('cpf', $digits)->firstOrFail();
    }

    abort(404);
  }

  private function resolveCompany(string $identifier): Company
  {
    if (is_numeric($identifier)) {
      return Company::query()->findOrFail((int) $identifier);
    }

    $digits = preg_replace('/\D/', '', $identifier);

    if ($digits !== '') {
      return Company::query()->where('cnpj', $digits)->firstOrFail();
    }

    abort(404);
  }

  private function resolveBillingEntry(string $identifier): RentalBillingQueueEntry
  {
    if (is_numeric($identifier)) {
      return RentalBillingQueueEntry::query()->findOrFail((int) $identifier);
    }

    return RentalBillingQueueEntry::query()->where('codigo', $identifier)->firstOrFail();
  }

  private function resolveYard(string $identifier): Yard
  {
    if (is_numeric($identifier)) {
      return Yard::query()->findOrFail((int) $identifier);
    }

    return Yard::query()->where('nome', $identifier)->firstOrFail();
  }

  private function resolveCategory(string $identifier): EquipmentCategory
  {
    if (is_numeric($identifier)) {
      return EquipmentCategory::query()->findOrFail((int) $identifier);
    }

    return EquipmentCategory::query()->where('nome', $identifier)->firstOrFail();
  }

  private function resolvePart(string $identifier): PartCatalogItem
  {
    if (is_numeric($identifier)) {
      return PartCatalogItem::query()->findOrFail((int) $identifier);
    }

    return PartCatalogItem::query()
      ->where('codigo_peca', $identifier)
      ->orWhere('codigo_alternativo', $identifier)
      ->firstOrFail();
  }
}
