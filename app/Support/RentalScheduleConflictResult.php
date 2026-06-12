<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class RentalScheduleConflictResult
{
    /** @param  Collection<int, \App\Models\Domain\Rental\Rental>  $overlapping */
    public function __construct(
        public readonly bool $hasConflict,
        public readonly string $message,
        public readonly Collection $overlapping,
        public readonly bool $isFutureReservation = false,
    ) {}

    public static function none(bool $isFuture = false): self
    {
        return new self(false, '', collect(), $isFuture);
    }

    /** @param  Collection<int, \App\Models\Domain\Rental\Rental>  $overlapping */
    public static function conflict(string $message, Collection $overlapping, bool $isFuture = false): self
    {
        return new self(true, $message, $overlapping, $isFuture);
    }
}
