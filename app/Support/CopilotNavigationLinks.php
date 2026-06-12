<?php

namespace App\Support;

class CopilotNavigationLinks
{
    /** @param  array{status_scope?: string, category_id?: int|string|null, search?: string|null, overdue_only?: bool}  $filters */
    public static function rentalsPanel(array $filters = []): string
    {
        $params = array_filter([
            'aba' => 'painel',
            'escopo' => $filters['status_scope'] ?? null,
            'categoria' => $filters['category_id'] ?? null,
            'busca' => $filters['search'] ?? null,
            'atrasados' => ! empty($filters['overdue_only']) ? 1 : null,
        ], fn ($value) => $value !== null && $value !== '');

        return route('rentals.index', $params);
    }

    /** @param  array{status?: string|null, q?: string|null}  $filters */
    public static function rentalsList(array $filters = []): string
    {
        $params = array_filter([
            'aba' => 'lista',
            'status' => $filters['status'] ?? null,
            'q' => $filters['q'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return route('rentals.index', $params);
    }

    public static function financeReceivables(?string $query = null): string
    {
        return route('finance.receivables', array_filter(['q' => $query]));
    }

    public static function billingQueue(): string
    {
        return route('finance.billing-queue');
    }

    public static function logisticsDaily(?string $date = null): string
    {
        return route('logistics.daily', array_filter(['data' => $date]));
    }

    public static function activeWorksMap(?string $region = null): string
    {
        return route('logistics.works-map', array_filter(['regiao' => $region]));
    }

    public static function assets(?string $query = null): string
    {
        return route('assets.index', array_filter(['q' => $query]));
    }

    public static function customers(?string $query = null): string
    {
        return route('customers.index', array_filter(['q' => $query]));
    }

    /** @param  array{status?: string|null, q?: string|null}  $filters */
    public static function quotes(array $filters = []): string
    {
        $params = array_filter([
            'search' => $filters['q'] ?? null,
            'statusFilter' => $filters['status'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return route('quotes.index', $params);
    }

    public static function yards(?string $query = null): string
    {
        return route('logistics.yards.index', array_filter(['search' => $query]));
    }

    /** @param  array{q?: string|null, status?: string|null, view?: string|null}  $filters */
    public static function maintenance(array $filters = []): string
    {
        $params = array_filter([
            'search' => $filters['q'] ?? null,
            'statusFilter' => $filters['status'] ?? null,
            'aba' => $filters['view'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return route('maintenance.index', $params);
    }

    public static function pricing(): string
    {
        return route('fleet.pricing.index');
    }

    public static function categories(?string $query = null): string
    {
        return route('fleet.categories.index', array_filter(['q' => $query]));
    }

    public static function models(?string $query = null): string
    {
        return route('fleet.models.index', array_filter(['q' => $query]));
    }

    public static function partsCatalog(?string $query = null): string
    {
        return route('maintenance.parts.index', array_filter(['search' => $query]));
    }

    public static function preventiveRules(): string
    {
        return route('maintenance.preventive.index');
    }

    public static function commercialReport(?string $dateFrom = null, ?string $dateTo = null): string
    {
        return route('reports.commercial', array_filter([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]));
    }

    public static function financialAnalysis(?string $dateFrom = null, ?string $dateTo = null): string
    {
        return route('reports.financial-analysis', array_filter([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]));
    }

    public static function fleetAnalytics(?string $dateFrom = null, ?string $dateTo = null, ?string $tab = null): string
    {
        return route('reports.fleet', array_filter([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'tab' => $tab,
        ]));
    }

    public static function adminUsers(): string
    {
        return route('admin.users.index');
    }

    public static function adminOperatingCompanies(): string
    {
        return route('admin.companies.index');
    }

    public static function adminAudit(): string
    {
        return route('admin.audit.index');
    }

    public static function adminAgentLogs(): string
    {
        return route('admin.agent-logs.index');
    }

    public static function adminAgentMetrics(): string
    {
        return route('admin.agent-metrics.index');
    }
}
