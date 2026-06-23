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

        $rentals = $this->findRentalsForSearch($term, 2);

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

        $rentalLimit = $this->looksLikeContractNumber($term) ? min(4, $limit) : 2;

        foreach ($this->matchingRentals($term)->take($rentalLimit) as $rental) {
            $results->push([
                'type' => 'contrato',
                'label' => $rental['codigo'],
                'subtitle' => $rental['customer'].' · '.$rental['status_label']
                    .($rental['asset_codigo'] ? ' · '.$rental['asset_codigo'] : ''),
                'url' => $rental['url'],
                'hint' => 'Ficha do contrato',
                'count' => null,
            ]);
        }

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

    /** @return Collection<int, array{codigo: string, customer: string, status_label: string, asset_codigo: ?string, url: string}> */
    private function matchingRentals(string $term): Collection
    {
        return $this->findRentalsForSearch($term, 10)
            ->map(fn (Rental $rental) => $this->mapRentalRow($rental));
    }

    /** @return Collection<int, Rental> */
    private function findRentalsForSearch(string $term, int $limit): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $upper = strtoupper($term);

        $exact = Rental::query()
            ->with(['customer', 'asset'])
            ->where('codigo', $upper)
            ->limit($limit)
            ->get();

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        if (preg_match('/^\d+$/', $term)) {
            $padded = 'LOC-'.str_pad($term, 6, '0', STR_PAD_LEFT);
            $paddedMatch = Rental::query()
                ->with(['customer', 'asset'])
                ->where('codigo', $padded)
                ->limit($limit)
                ->get();

            if ($paddedMatch->isNotEmpty()) {
                return $paddedMatch;
            }
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $numeric = preg_replace('/\D/', '', $term);

        return Rental::query()
            ->with(['customer', 'asset'])
            ->latest()
            ->where(function ($query) use ($like, $numeric) {
                $query->where('codigo', 'like', $like);

                if ($numeric !== '' && strlen($numeric) >= 3) {
                    $query->orWhere('codigo', 'like', '%'.$numeric.'%');
                }

                $query->orWhereHas('customer', fn ($customer) => $customer->where('nome', 'like', $like))
                    ->orWhereHas('asset', fn ($asset) => $asset->where('codigo_patrimonio', 'like', $like));
            })
            ->limit(50)
            ->get()
            ->filter(fn (Rental $rental) => TextSearch::matchesAny(
                $term,
                $rental->codigo,
                $rental->customer?->nome,
                $rental->asset?->codigo_patrimonio,
                $rental->local_obra,
            ))
            ->take($limit)
            ->values();
    }

    /** @return array{codigo: string, customer: string, status_label: string, asset_codigo: ?string, url: string} */
    private function mapRentalRow(Rental $rental): array
    {
        return [
            'codigo' => $rental->codigo,
            'customer' => $rental->customer?->nome ?? '—',
            'status_label' => $rental->statusEnum()->label(),
            'asset_codigo' => $rental->asset?->codigo_patrimonio,
            'url' => route('rentals.show', $rental),
        ];
    }

    private function looksLikeContractNumber(string $term): bool
    {
        return (bool) preg_match('/^(LOC-)?\d+/i', trim($term));
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
