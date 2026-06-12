<?php

namespace App\Agent\Concerns;

use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\Domain\Rental\RentalQuote;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartCatalogItem;
use InvalidArgumentException;

trait ResolvesAgentEntities
{
  /** @param  array<string, mixed>  $input */
  protected function resolveRental(array $input): Rental
  {
    if (! empty($input['rental_id'])) {
      return Rental::query()->findOrFail((int) $input['rental_id']);
    }

    if (! empty($input['rental_codigo'])) {
      $rental = Rental::query()->where('codigo', $input['rental_codigo'])->first();

      if ($rental) {
        return $rental;
      }
    }

    throw new InvalidArgumentException('Informe rental_id ou rental_codigo.');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolveAsset(array $input): Asset
  {
    if (! empty($input['asset_id'])) {
      return Asset::query()->findOrFail((int) $input['asset_id']);
    }

    if (! empty($input['asset_codigo'])) {
      $asset = Asset::query()->where('codigo_patrimonio', $input['asset_codigo'])->first();

      if ($asset) {
        return $asset;
      }
    }

    throw new InvalidArgumentException('Informe asset_id ou asset_codigo (codigo_patrimonio).');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolveCustomer(array $input): Customer
  {
    if (! empty($input['customer_id'])) {
      return Customer::query()->findOrFail((int) $input['customer_id']);
    }

    if (! empty($input['customer_cpf_cnpj'])) {
      $digits = preg_replace('/\D/', '', (string) $input['customer_cpf_cnpj']);
      $customer = Customer::query()->where('cpf_cnpj', $digits)->first();

      if ($customer) {
        return $customer;
      }
    }

    if (! empty($input['customer_name'])) {
      $name = trim((string) $input['customer_name']);
      $matches = Customer::query()
        ->where('nome', 'like', '%'.$name.'%')
        ->orderBy('nome')
        ->limit(2)
        ->get();

      if ($matches->count() === 1) {
        return $matches->first();
      }

      if ($matches->count() > 1) {
        throw new InvalidArgumentException("Múltiplos clientes encontrados para \"{$name}\". Informe customer_id ou customer_cpf_cnpj.");
      }

      throw new InvalidArgumentException("Nenhum cliente encontrado para \"{$name}\".");
    }

    throw new InvalidArgumentException('Informe customer_id, customer_cpf_cnpj ou customer_name.');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolveBillingEntry(array $input): RentalBillingQueueEntry
  {
    if (! empty($input['entry_id'])) {
      return RentalBillingQueueEntry::query()->findOrFail((int) $input['entry_id']);
    }

    if (! empty($input['entry_codigo'])) {
      $entry = RentalBillingQueueEntry::query()->where('codigo', $input['entry_codigo'])->first();

      if ($entry) {
        return $entry;
      }
    }

    throw new InvalidArgumentException('Informe entry_id ou entry_codigo.');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolveReceivableTitle(array $input): ReceivableTitle
  {
    if (! empty($input['title_id'])) {
      return ReceivableTitle::query()->findOrFail((int) $input['title_id']);
    }

    if (! empty($input['title_codigo'])) {
      $title = ReceivableTitle::query()->where('codigo', $input['title_codigo'])->first();

      if ($title) {
        return $title;
      }
    }

    throw new InvalidArgumentException('Informe title_id ou title_codigo.');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolveMaintenanceOrder(array $input): MaintenanceOrder
  {
    if (! empty($input['order_id'])) {
      return MaintenanceOrder::query()->findOrFail((int) $input['order_id']);
    }

    if (! empty($input['order_codigo'])) {
      $order = MaintenanceOrder::query()->where('codigo', $input['order_codigo'])->first();

      if ($order) {
        return $order;
      }
    }

    throw new InvalidArgumentException('Informe order_id ou order_codigo.');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolveQuote(array $input): RentalQuote
  {
    if (! empty($input['quote_id'])) {
      return RentalQuote::query()->findOrFail((int) $input['quote_id']);
    }

    if (! empty($input['quote_codigo'])) {
      $quote = RentalQuote::query()->where('codigo', $input['quote_codigo'])->first();

      if ($quote) {
        return $quote;
      }
    }

    throw new InvalidArgumentException('Informe quote_id ou quote_codigo.');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolvePerson(array $input): Person
  {
    if (! empty($input['person_id'])) {
      return Person::query()->findOrFail((int) $input['person_id']);
    }

    $digits = preg_replace('/\D/', '', (string) ($input['person_cpf'] ?? ''));

    if ($digits !== '') {
      $person = Person::query()->where('cpf', $digits)->first();

      if ($person) {
        return $person;
      }
    }

    throw new InvalidArgumentException('Informe person_id ou person_cpf.');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolveCompany(array $input): Company
  {
    if (! empty($input['company_id'])) {
      return Company::query()->findOrFail((int) $input['company_id']);
    }

    $digits = preg_replace('/\D/', '', (string) ($input['company_cnpj'] ?? ''));

    if ($digits !== '') {
      $company = Company::query()->where('cnpj', $digits)->first();

      if ($company) {
        return $company;
      }
    }

    throw new InvalidArgumentException('Informe company_id ou company_cnpj.');
  }

  /** @param  array<string, mixed>  $input @return list<array{type: string, id: int}> */
  protected function affectedResourcesForRental(array $input): array
  {
    try {
      $rental = $this->resolveRental($input);
      $resources = [['type' => 'rental', 'id' => $rental->id]];

      if ($rental->asset_id) {
        $resources[] = ['type' => 'asset', 'id' => (int) $rental->asset_id];
      }

      return $resources;
    } catch (\Throwable) {
      return [];
    }
  }

  /** @param  array<string, mixed>  $input @return list<array{type: string, id: int}> */
  protected function affectedResourcesForMaintenanceOrder(array $input): array
  {
    try {
      $order = $this->resolveMaintenanceOrder($input);
      $resources = [['type' => 'maintenance_order', 'id' => $order->id]];

      if ($order->asset_id) {
        $resources[] = ['type' => 'asset', 'id' => (int) $order->asset_id];
      }

      if ($order->rental_id) {
        $resources[] = ['type' => 'rental', 'id' => (int) $order->rental_id];
      }

      return $resources;
    } catch (\Throwable) {
      return [];
    }
  }

  /** @param  array<string, mixed>  $input @return list<array{type: string, id: int}> */
  protected function affectedResourcesForBillingEntry(array $input): array
  {
    try {
      $entry = $this->resolveBillingEntry($input);
      $resources = [['type' => 'billing_entry', 'id' => $entry->id]];

      if ($entry->rental_id) {
        $resources[] = ['type' => 'rental', 'id' => (int) $entry->rental_id];
      }

      return $resources;
    } catch (\Throwable) {
      return [];
    }
  }

  /** @param  array<string, mixed>  $input @return list<array{type: string, id: int}> */
  protected function affectedResourcesForReceivableTitle(array $input): array
  {
    try {
      $title = $this->resolveReceivableTitle($input);
      $resources = [['type' => 'receivable_title', 'id' => $title->id]];

      if ($title->customer_id) {
        $resources[] = ['type' => 'customer', 'id' => (int) $title->customer_id];
      }

      if ($title->rental_id) {
        $resources[] = ['type' => 'rental', 'id' => (int) $title->rental_id];
      }

      return $resources;
    } catch (\Throwable) {
      return [];
    }
  }

  /** @param  array<string, mixed>  $input */
  protected function resolvePart(array $input): PartCatalogItem
  {
    if (! empty($input['part_id'])) {
      return PartCatalogItem::query()->findOrFail((int) $input['part_id']);
    }

    if (! empty($input['part_codigo'])) {
      $code = trim((string) $input['part_codigo']);
      $part = PartCatalogItem::query()
        ->where('codigo_peca', $code)
        ->orWhere('codigo_alternativo', $code)
        ->first();

      if ($part) {
        return $part;
      }
    }

    throw new InvalidArgumentException('Informe part_id ou part_codigo.');
  }

  /** @param  array<string, mixed>  $input */
  protected function resolveYard(array $input): Yard
  {
    if (! empty($input['yard_id'])) {
      return Yard::query()->findOrFail((int) $input['yard_id']);
    }

    $name = trim((string) ($input['yard_name'] ?? ''));

    if ($name !== '') {
      $matches = Yard::query()
        ->where('nome', 'like', '%'.$name.'%')
        ->orderByDesc('principal')
        ->orderBy('nome')
        ->limit(2)
        ->get();

      if ($matches->count() === 1) {
        return $matches->first();
      }

      if ($matches->count() > 1) {
        throw new InvalidArgumentException("Múltiplos pátios para \"{$name}\". Informe yard_id.");
      }

      throw new InvalidArgumentException("Pátio não encontrado: \"{$name}\".");
    }

    throw new InvalidArgumentException('Informe yard_id ou yard_name.');
  }

  /** @param  array<string, mixed>  $input @return list<array{type: string, id: int}> */
  protected function affectedResourcesForAssetOpenMaintenance(array $input): array
  {
    try {
      $asset = $this->resolveAsset($input);
      $resources = [['type' => 'asset', 'id' => $asset->id]];

      if (! empty($input['rental_id']) || ! empty($input['rental_codigo'])) {
        $rental = $this->resolveRental($input);
        $resources[] = ['type' => 'rental', 'id' => $rental->id];
      }

      return $resources;
    } catch (\Throwable) {
      return [];
    }
  }
}
