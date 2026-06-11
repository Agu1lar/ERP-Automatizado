<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OperatingCompanyRelations
{
    /**
     * @param  class-string<Model>  $related
     */
    public static function belongsTo(
        Model $parent,
        string $related,
        string $relation,
        ?string $foreignKey = null,
        ?string $ownerKey = null,
    ): BelongsTo {
        return $parent->belongsTo($related, $foreignKey, $ownerKey, $relation)
            ->withoutGlobalScope('operating_company');
    }
}
