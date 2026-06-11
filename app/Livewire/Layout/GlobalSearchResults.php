<?php

namespace App\Livewire\Layout;

use App\Services\GlobalSearchService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class GlobalSearchResults extends Component
{
    #[Url(as: 'q')]
    public string $q = '';

    public function render(): View
    {
        $service = app(GlobalSearchService::class);
        $results = $service->fullResults($this->q);

        $totalAssets = $results['categories']->sum('total') + $results['assets']->count();

        return view('livewire.layout.global-search-results', [
            'results' => $results,
            'totalAssets' => $totalAssets,
            'hasResults' => $totalAssets > 0
                || $results['customers']->isNotEmpty()
                || $results['rentals']->isNotEmpty(),
        ]);
    }
}
