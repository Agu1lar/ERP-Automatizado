<?php

namespace App\Models\Domain\Person;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyContact extends Model
{
    protected $fillable = [
        'company_id',
        'nome',
        'cargo',
        'telefone',
        'principal',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function displayLabel(): string
    {
        $parts = array_filter([$this->nome, $this->cargo, $this->telefone]);

        return implode(' · ', $parts);
    }
}
