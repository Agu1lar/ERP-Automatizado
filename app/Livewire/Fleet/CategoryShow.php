<?php

namespace App\Livewire\Fleet;

use App\Models\Domain\Fleet\EquipmentCategory;
use App\Support\CategoryAssetBoard;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CategoryShow extends Component
{
    use AuthorizesRequests;

    public EquipmentCategory $category;

    public function mount(EquipmentCategory $category): void
    {
        $this->authorize('view', $category);
        $this->category = $category;
    }

    public function render(): View
    {
        $board = CategoryAssetBoard::forCategory($this->category);

        return view('livewire.fleet.category-show', [
            'board' => $board,
            'groupLabels' => CategoryAssetBoard::groupLabels(),
            'totalAssets' => $board->flatten(1)->count(),
        ]);
    }
}
