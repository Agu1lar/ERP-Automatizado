<?php

namespace App\Agent\Concerns;

use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Maintenance\MaintenanceOrder;
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
}
