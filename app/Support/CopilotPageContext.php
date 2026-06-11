<?php

namespace App\Support;

use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class CopilotPageContext
{
    /** @return array{route: string, url: string, label: string, detail: string|null, summary: string, parameters: array<string, mixed>} */
    public static function fromRequest(Request $request): array
    {
        $route = $request->route();

        if ($route === null) {
            return self::fallback($request->fullUrl());
        }

        return self::build(
            (string) ($route->getName() ?? ''),
            $request->fullUrl(),
            $route->parameters(),
        );
    }

    /** @return array{route: string, url: string, label: string, detail: string|null, summary: string, parameters: array<string, mixed>} */
    public static function fromUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        $uri = $path.($query ? '?'.$query : '');

        try {
            $request = Request::create($uri, 'GET');
            $matched = Route::getRoutes()->match($request);
            $request->setRouteResolver(fn () => $matched);

            return self::fromRequest($request);
        } catch (\Throwable) {
            return self::fallback($url);
        }
    }

    public static function formatForAgent(string $summary, string $route, string $url): string
    {
        if ($summary === '') {
            return '';
        }

        return "[Contexto da tela: {$summary} | rota={$route} | url={$url}]";
    }

    /** @param  array<string, mixed>  $parameters */
    private static function build(string $routeName, string $url, array $parameters): array
    {
        $label = self::labelForRoute($routeName);
        $detail = self::detailForParameters($parameters);
        $summary = trim($label.($detail ? " — {$detail}" : ''));

        return [
            'route' => $routeName,
            'url' => $url,
            'label' => $label,
            'detail' => $detail,
            'summary' => $summary !== '' ? $summary : 'Página do sistema',
            'parameters' => $parameters,
        ];
    }

    /** @return array{route: string, url: string, label: string, detail: string|null, summary: string, parameters: array<string, mixed>} */
    private static function fallback(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return [
            'route' => '',
            'url' => $url,
            'label' => 'Página',
            'detail' => $path,
            'summary' => $path,
            'parameters' => [],
        ];
    }

    private static function labelForRoute(string $routeName): string
    {
        return match ($routeName) {
            'dashboard' => 'Dashboard',
            'assets.index' => 'Patrimônios',
            'assets.show' => 'Ficha do patrimônio',
            'rentals.index' => 'Locações',
            'rentals.show' => 'Ficha da locação',
            'quotes.index' => 'Orçamentos',
            'customers.index' => 'Clientes',
            'customers.show' => 'Ficha do cliente',
            'maintenance.index' => 'Manutenção',
            'maintenance.show' => 'Ordem de serviço',
            'finance.receivables' => 'Títulos a receber',
            'finance.billing-queue' => 'A faturar',
            'finance.delinquency' => 'Inadimplência',
            'finance.cashflow' => 'Fluxo de caixa',
            'logistics.daily' => 'Lista do dia (logística)',
            'logistics.yards.index' => 'Pátios',
            'reports.commercial' => 'Relatório comercial',
            'reports.financial-analysis' => 'Análise financeira',
            'fleet.categories.index' => 'Categorias',
            'fleet.models.index' => 'Modelos',
            'fleet.pricing.index' => 'Tabela de preços',
            'people.index' => 'Pessoas',
            'people.show' => 'Ficha da pessoa',
            'companies.index' => 'Empresas',
            'search.results' => 'Busca global',
            'yard.scan' => 'Modo pátio',
            default => $routeName !== '' ? str_replace('.', ' › ', $routeName) : 'Página',
        };
    }

    /** @param  array<string, mixed>  $parameters */
    private static function detailForParameters(array $parameters): ?string
    {
        if (isset($parameters['rental'])) {
            return self::resolveRentalLabel($parameters['rental']);
        }

        if (isset($parameters['asset'])) {
            return self::resolveAssetLabel($parameters['asset']);
        }

        if (isset($parameters['customer'])) {
            return self::resolveCustomerLabel($parameters['customer']);
        }

        if (isset($parameters['order'])) {
            return self::resolveOrderLabel($parameters['order']);
        }

        return null;
    }

    private static function resolveRentalLabel(mixed $rental): ?string
    {
        if ($rental instanceof Rental) {
            return $rental->codigo;
        }

        if (is_numeric($rental)) {
            return Rental::query()->find($rental)?->codigo;
        }

        return is_string($rental) ? $rental : null;
    }

    private static function resolveAssetLabel(mixed $asset): ?string
    {
        if ($asset instanceof Asset) {
            return $asset->codigo_patrimonio;
        }

        if (is_numeric($asset)) {
            return Asset::query()->find($asset)?->codigo_patrimonio;
        }

        return is_string($asset) ? $asset : null;
    }

    private static function resolveCustomerLabel(mixed $customer): ?string
    {
        if ($customer instanceof Customer) {
            return $customer->nome;
        }

        if (is_numeric($customer)) {
            return Customer::query()->find($customer)?->nome;
        }

        return is_string($customer) ? $customer : null;
    }

    private static function resolveOrderLabel(mixed $order): ?string
    {
        if ($order instanceof MaintenanceOrder) {
            return $order->codigo;
        }

        if (is_numeric($order)) {
            return MaintenanceOrder::query()->find($order)?->codigo;
        }

        return is_string($order) ? $order : null;
    }
}
