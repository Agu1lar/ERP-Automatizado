<?php

namespace App\Livewire\Layout;

use App\Services\GlobalSearchService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public bool $open = false;

    public function updatedQuery(): void
    {
        $this->open = strlen(trim($this->query)) >= 1;
    }

    public function submit(): void
    {
        $term = trim($this->query);

        if ($term === '') {
            return;
        }

        $service = app(GlobalSearchService::class);
        $directUrl = $service->resolveDirectUrl($term);

        if ($directUrl !== null) {
            $this->redirect($directUrl, navigate: true);

            return;
        }

        $this->redirect(route('search.results', ['q' => $term]), navigate: true);
    }

    public function clear(): void
    {
        $this->query = '';
        $this->open = false;
    }

    public function render(): View
    {
        return view('livewire.layout.global-search', [
            'suggestions' => app(GlobalSearchService::class)->quickSuggestions($this->query),
        ]);
    }
}
