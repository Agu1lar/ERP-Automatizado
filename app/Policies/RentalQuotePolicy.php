<?php

namespace App\Policies;

use App\Models\Domain\Rental\RentalQuote;
use App\Models\User;

class RentalQuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rentals.view');
    }

    public function view(User $user, RentalQuote $quote): bool
    {
        return $user->can('rentals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('rentals.reserve');
    }

    public function update(User $user, RentalQuote $quote): bool
    {
        return $user->can('rentals.reserve') && $quote->statusEnum()->canEdit();
    }

    public function send(User $user, RentalQuote $quote): bool
    {
        return $user->can('rentals.reserve') && $quote->statusEnum()->canEdit();
    }

    public function convert(User $user, RentalQuote $quote): bool
    {
        return $user->can('rentals.reserve') && $quote->statusEnum()->canConvert();
    }

    public function cancel(User $user, RentalQuote $quote): bool
    {
        return $user->can('rentals.reserve')
            && ! in_array($quote->statusEnum(), [\App\Enums\RentalQuoteStatus::Convertido, \App\Enums\RentalQuoteStatus::Cancelado], true);
    }
}
