<?php

namespace App\Models\Domain\Person;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyEmail extends Model
{
    protected $fillable = [
        'company_id',
        'email',
        'rotulo',
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
        return filled($this->rotulo)
            ? "{$this->rotulo}: {$this->email}"
            : $this->email;
    }
}
