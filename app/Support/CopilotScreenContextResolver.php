<?php

namespace App\Support;

use App\Agent\AgentContextBuilder;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\User;

/**
 * Enriquece o prompt do copiloto com JSON estruturado da ficha aberta na tela.
 */
class CopilotScreenContextResolver
{
    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null
     */
    public function resolve(User $user, string $routeName, array $parameters): ?array
    {
        if ($routeName === '') {
            return null;
        }

        return match ($routeName) {
            'rentals.show' => $this->rentalContext($user, $parameters['rental'] ?? null),
            'assets.show' => $this->assetContext($user, $parameters['asset'] ?? null),
            'customers.show' => $this->customerContext($user, $parameters['customer'] ?? null),
            'maintenance.show' => $this->maintenanceContext($user, $parameters['order'] ?? null),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $parameters */
    public function formatForAgent(
        User $user,
        string $summary,
        string $routeName,
        string $url,
        array $parameters = [],
    ): string {
        $text = CopilotPageContext::formatForAgent($summary, $routeName, $url);

        if ($text === '') {
            return '';
        }

        $structured = $this->resolve($user, $routeName, $parameters);

        if ($structured === null) {
            return $text;
        }

        $json = json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $text."\n\n[Contexto estruturado da ficha atual — use para responder sem pedir código novamente]\n```json\n{$json}\n```";
    }

    /** @return array<string, mixed>|null */
    private function rentalContext(User $user, mixed $rental): ?array
    {
        $model = $this->resolveRental($rental);

        if (! $model || ! $user->can('view', $model)) {
            return null;
        }

        return $this->contextBuilder->rental($model);
    }

    /** @return array<string, mixed>|null */
    private function assetContext(User $user, mixed $asset): ?array
    {
        $model = $this->resolveAsset($asset);

        if (! $model || ! $user->can('view', $model)) {
            return null;
        }

        return $this->contextBuilder->asset($model);
    }

    /** @return array<string, mixed>|null */
    private function customerContext(User $user, mixed $customer): ?array
    {
        $model = $this->resolveCustomer($customer);

        if (! $model || ! $user->can('view', $model)) {
            return null;
        }

        return $this->contextBuilder->customer($model);
    }

    /** @return array<string, mixed>|null */
    private function maintenanceContext(User $user, mixed $order): ?array
    {
        $model = $this->resolveMaintenanceOrder($order);

        if (! $model || ! $user->can('view', $model)) {
            return null;
        }

        return $this->contextBuilder->maintenanceOrder($model);
    }

    private function resolveRental(mixed $rental): ?Rental
    {
        if ($rental instanceof Rental) {
            return $rental;
        }

        if (is_numeric($rental)) {
            return Rental::query()->find((int) $rental);
        }

        if (is_string($rental) && $rental !== '') {
            return Rental::query()->where('codigo', $rental)->first();
        }

        return null;
    }

    private function resolveAsset(mixed $asset): ?Asset
    {
        if ($asset instanceof Asset) {
            return $asset;
        }

        if (is_numeric($asset)) {
            return Asset::query()->find((int) $asset);
        }

        if (is_string($asset) && $asset !== '') {
            return Asset::query()->where('codigo_patrimonio', $asset)->first();
        }

        return null;
    }

    private function resolveCustomer(mixed $customer): ?Customer
    {
        if ($customer instanceof Customer) {
            return $customer;
        }

        if (is_numeric($customer)) {
            return Customer::query()->find((int) $customer);
        }

        return null;
    }

    private function resolveMaintenanceOrder(mixed $order): ?MaintenanceOrder
    {
        if ($order instanceof MaintenanceOrder) {
            return $order;
        }

        if (is_numeric($order)) {
            return MaintenanceOrder::query()->find((int) $order);
        }

        if (is_string($order) && $order !== '') {
            return MaintenanceOrder::query()->where('codigo', $order)->first();
        }

        return null;
    }
}
