<?php

namespace Tests\Support\Livewire;

use App\Livewire\Concerns\ArchivesRecords;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/** Minimal component to exercise {@see ArchivesRecords} without page layout or pagination. */
class ArchivesRecordsHarness extends Component
{
    use ArchivesRecords;
    use AuthorizesRequests;

    public function render(): string
    {
        return '<div>archive-harness</div>';
    }
}
