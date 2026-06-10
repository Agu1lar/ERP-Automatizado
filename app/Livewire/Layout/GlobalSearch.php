<?php

namespace App\Livewire\Layout;

use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Support\TextSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public bool $open = false;

    public function updatedQuery(): void
    {
        $this->open = strlen(trim($this->query)) >= 1;
    }

    public function clear(): void
    {
        $this->query = '';
        $this->open = false;
    }

    public function render(): View
    {
        return view('livewire.layout.global-search', [
            'suggestions' => $this->suggestions(),
        ]);
    }

    /** @return Collection<int, array{type: string, label: string, subtitle: string, url: string}> */
    private function suggestions(): Collection
    {
        $term = trim($this->query);

        if (strlen($term) < 1) {
            return collect();
        }

        $results = collect();

        Asset::query()
            ->with('equipmentModel.category')
            ->latest()
            ->limit(80)
            ->get()
            ->each(function (Asset $asset) use ($term, $results) {
                if (TextSearch::matchesAny(
                    $term,
                    $asset->codigo_patrimonio,
                    $asset->serie,
                    $asset->equipmentModel?->marca,
                    $asset->equipmentModel?->modelo,
                    $asset->equipmentModel?->category?->nome,
                    $asset->localizacao,
                )) {
                    $results->push([
                        'type' => 'patrimonio',
                        'label' => $asset->codigo_patrimonio,
                        'subtitle' => $asset->equipmentModel->displayName(),
                        'url' => route('assets.show', $asset),
                    ]);
                }
            });

        if ($results->count() < 5) {
            EquipmentModel::query()
                ->with('category')
                ->where('ativo', true)
                ->limit(80)
                ->get()
                ->each(function (EquipmentModel $model) use ($term, $results) {
                    if ($results->count() >= 5) {
                        return;
                    }

                    if (TextSearch::matchesAny(
                        $term,
                        $model->marca,
                        $model->modelo,
                        $model->category?->nome,
                    )) {
                        $results->push([
                            'type' => 'modelo',
                            'label' => $model->displayName(),
                            'subtitle' => $model->category->nome,
                            'url' => route('fleet.models.index', ['search' => $model->marca]),
                        ]);
                    }
                });
        }

        if ($results->count() < 5) {
            Customer::query()
                ->where('ativo', true)
                ->limit(80)
                ->get()
                ->each(function (Customer $customer) use ($term, $results) {
                    if ($results->count() >= 5) {
                        return;
                    }

                    if (TextSearch::matchesAny(
                        $term,
                        $customer->nome,
                        $customer->cpf_cnpj,
                        $customer->contato,
                        $customer->telefone,
                    )) {
                        $results->push([
                            'type' => 'cliente',
                            'label' => $customer->nome,
                            'subtitle' => $customer->formattedDocument(),
                            'url' => route('customers.index'),
                        ]);
                    }
                });
        }

        return $results->unique(fn (array $item) => $item['type'].'|'.$item['label'])->take(5)->values();
    }
}
