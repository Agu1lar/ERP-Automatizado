<?php

namespace App\Support;

use App\Models\Domain\Rental\Rental;

class RentalFichaNavigation
{
    public static function flashReturnLink(?Rental $rental): void
    {
        if ($rental === null) {
            return;
        }

        session()->flash('return_to_rental_url', route('rentals.show', $rental));
        session()->flash('return_to_rental_label', "Voltar à ficha {$rental->codigo}");
        session()->flash('return_to_rental_tab_title', $rental->codigo);
    }
}
