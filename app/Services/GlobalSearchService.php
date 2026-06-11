<?php

namespace App\Services;

use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Support\TextSearch;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    /**
     * Redireciona direto quando há correspondência única e inequívoca (ex.: código de patrimônio).
     */
    public function resolveDirectUrl(string $query): ?string
    {
        $term = trim($query);

        if ($term === '') {
            return null;
        }

        $assets = Asset::query()
            ->with(['equipmentModel.category', 'rentals' => fn ($q) => $q->active()->with('customer')])
            ->where('codigo_patrimonio', $term)
            ->get();

        if ($assets->count() === 1) {
            return $this->mapAssetRow($assets->first())['primary_url'];
        }

        $rentals = Rental::query()
            ->where('codigo', $term)
            ->limit(2)
            ->get();

        if ($rentals->count() === 1) {
            return route('rentals.show', $rentals->first());
        }

        return null;
    }

    /**
     * @return array{
     *     query: string,
     *     categories: Collection<int, array{
     *         id: int,
     *         nome: string,
     *         total: int,
     *         assets: Collection<int, array<string, mixed>>
     *     }>,
     *     assets: Collection<int, array<string, mixed>>,
     *     customers: Collection<int, array<string, mixed>>,
     *     rentals: Collection<int, array<string, mixed>>
     * }
     */
    public function fullResults(string $query): array
    {
        $term = trim($query);

        $categories = $this->matchingCategories($term)
            ->map(fn (EquipmentCategory $category) => [
                'id' => $category->id,
                'nome' => $category->nome,
                'total' => $this->assetsForCategory($category)->count(),
                'assets' => $this->assetsForCategory($category)->map(fn (Asset $asset) => $this->mapAssetRow($asset)),
            ]);

        $categoryAssetIds = $categories
            ->flatMap(fn (array $category) => $category['assets']->pluck('asset_id'))
            ->unique();

        return [
            'query' => $term,
            'categories' => $categories,
            'assets' => $this->matchingAssets($term)
                ->reject(fn (Asset $asset) => $categoryAssetIds->contains($asset->id))
                ->map(fn (Asset $asset) => $this->mapAssetRow($asset))
                ->values(),
            'customers' => $this->matchingCustomers($term),
            'rentals' => $this->matchingRentals($term),
        ];
    }

    /**
     * Sugestões rápidas para o dropdown da barra de busca.
     *
     * @return Collection<int, array{
     *     type: string,
     *     label: string,
     *     subtitle: string,
     *     url: string,
     *     hint: string|null,
     *     count: int|null
     * }>
     */
    public function quickSuggestions(string $query, int $limit = 8): Collection
    {
        $term = trim($query);

        if ($term === '') {
            return collect();
        }

        $results = collect();

        foreach ($this->matchingCategories($term)->take(2) as $category) {
            $count = $this->assetsForCategory($category)->count();
            $results->push([
                'type' => 'categoria',
                'label' => $category->nome,
                'subtitle' => $count.' patrimônio(s) nesta categoria',
                'url' => route('search.results', ['q' => $term]),
                'hint' => 'Enter para ver todos',
                'count' => $count,
            ]);
        }

        $this->matchingAssets($term)
            ->take(max(1, $limit - $results->count()))
            ->each(function (Asset $asset) use ($results, $limit) {
                if ($results->count() >= $limit) {
                    return false;
                }

                $row = $this->mapAssetRow($asset);
                $results->push([
                    'type' => 'patrimonio',
                    'label' => $row['codigo_patrimonio'],
                    'subtitle' => $row['model_name'].' · '.$row['status_label'],
                    'url' => $row['primary_url'],
                    'hint' => $row['primary_label'],
                    'count' => null,
                ]);
            });

        $this->matchingCustomers($term)
            ->take(max(1, $limit - $results->count()))
            ->each(function (array $customer) use ($results, $limit) {
                if ($results->count() >= $limit) {
                    return false;
                }

                $results->push([
                    'type' => 'cliente',
                    'label' => $customer['nome'],
                    'subtitle' => $customer['blocked']
                        ? ($customer['block_reason'] ?? 'Cliente bloqueado')
                        : ($customer['has_overdue']
                            ? 'Em atraso: R$ '.number_format($customer['overdue_balance'], 2, ',', '.')
                            : $customer['document']),
                    'url' => $customer['url'],
                    'hint' => $customer['blocked']
                        ? 'Cliente bloqueado'
                        : ($customer['has_overdue'] ? 'Títulos em atraso — bloqueio é decisão comercial' : null),
                    'count' => null,
                    'blocked' => $customer['blocked'],
                    'block_reason' => $customer['block_reason'],
                    'has_overdue' => $customer['has_overdue'],
                ]);
            });

        return $results->take($limit)->values();
    }

    /** @return Collection<int, EquipmentCategory> */
    public function matchingCategories(string $term): Collection
    {
        return EquipmentCategory::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get()
            ->filter(fn (EquipmentCategory $category) => TextSearch::matchesFlexible($category->nome, $term))
            ->values();
    }

    /** @return Collection<int, Asset> */
    public function matchingAssets(string $term): Collection
    {
        return Asset::query()
            ->with(['equipmentModel.category', 'rentals' => fn ($q) => $q->active()->with('customer')])
            ->orderBy('codigo_patrimonio')
            ->get()
            ->filter(fn (Asset $asset) => TextSearch::matchesAny(
                $term,
                $asset->codigo_patrimonio,
                $asset->serie,
                $asset->equipmentModel?->marca,
                $asset->equipmentModel?->modelo,
                $asset->equipmentModel?->category?->nome,
                $asset->localizacao,
            ))
            ->values();
    }

    /** @return Collection<int, Asset> */
    private function assetsForCategory(EquipmentCategory $category): Collection
    {
        return Asset::query()
            ->with(['equipmentModel', 'rentals' => fn ($q) => $q->active()->with('customer')])
            ->whereHas('equipmentModel', fn ($query) => $query->where('equipment_category_id', $category->id))
            ->orderBy('codigo_patrimonio')
            ->get();
    }

    /** @return Collection<int, array{nome: string, document: string, url: string, blocked: bool, block_reason: ?string}> */
    private function matchingCustomers(string $term): Collection
    {
        $finance = app(ReceivableTitleService::class);

        return Customer::query()
            ->where(function ($query) {
                $query->where('ativo', true)
                    ->orWhere('bloqueado', true);
            })
            ->orderBy('nome')
            ->get()
            ->filter(fn (Customer $customer) => TextSearch::matchesAny(
                $term,
                $customer->nome,
                $customer->cpf_cnpj,
                $customer->contato,
                $customer->telefone,
                $customer->email,
            ))
            ->map(fn (Customer $customer) => [
                'nome' => $customer->nome,
                'document' => $customer->formattedDocument(),
                'url' => route('customers.show', $customer),
                'blocked' => $customer->isBlockedForDisplay(),
                'block_reason' => $customer->rentalBlockReason(),
                'has_overdue' => $finance->customerHasOverdueTitles($customer),
                'overdue_balance' => $finance->customerOverdueBalance($customer),
            ])
            ->values();
    }

    /** @return Collection<int, array{codigo: string, customer: string, url: string}> */
    private function matchingRentals(string $term): Collection
    {
        return Rental::query()
            ->with('customer')
            ->latest()
            ->get()
            ->filter(fn (Rental $rental) => TextSearch::matchesAny(
                $term,
                $rental->codigo,
                $rental->customer?->nome,
            ))
            ->take(10)
            ->map(fn (Rental $rental) => [
                'codigo' => $rental->codigo,
                'customer' => $rental->customer?->nome ?? '—',
                'url' => route('rentals.show', $rental),
            ])
            ->values();
    }

    /** @return array<string, mixed> */
    public function mapAssetRow(Asset $asset): array
    {
        $rental = $asset->relationLoaded('rentals') && $asset->rentals->isNotEmpty()
            ? $asset->rentals->first()
            : $asset->activeRental();
        $status = $asset->statusEnum();

        return [
            'asset_id' => $asset->id,
            'codigo_patrimonio' => $asset->codigo_patrimonio,
            'model_name' => $asset->equipmentModel?->displayName() ?? '—',
            'category_name' => $asset->equipmentModel?->category?->nome ?? '—',
            'status' => $status,
            'status_label' => $status->label(),
            'localizacao' => $asset->localizacao,
            'rental_codigo' => $rental?->codigo,
            'customer_nome' => $rental?->customer?->nome,
            'primary_url' => $rental
                ? route('rentals.show', $rental)
                : route('assets.show', $asset),
            'primary_label' => $rental ? 'Ficha da locação' : 'Ficha do patrimônio',
            'secondary_url' => $rental ? route('assets.show', $asset) : null,
            'secondary_label' => $rental ? 'Ver patrimônio' : null,
        ];
    }
}
