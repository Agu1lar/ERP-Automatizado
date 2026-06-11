<?php

namespace App\Models\Concerns;

use App\Models\Domain\Organization\OperatingCompany;
use App\Support\ActiveOperatingCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin Model */
trait BelongsToOperatingCompany
{
    public static function bootBelongsToOperatingCompany(): void
    {
        static::addGlobalScope('operating_company', function (Builder $builder): void {
            ActiveOperatingCompany::applyScope($builder);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('operating_company_id') === null) {
                $model->setAttribute(
                    'operating_company_id',
                    ActiveOperatingCompany::resolveIdForNewRecord(),
                );
            }
        });
    }

    public function operatingCompany(): BelongsTo
    {
        return $this->belongsTo(OperatingCompany::class);
    }

    public function scopeForOperatingCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope('operating_company')
            ->where($this->getTable().'.operating_company_id', $companyId);
    }
}
