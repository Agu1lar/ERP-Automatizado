<?php

namespace App\Models\Domain\Logistics;

use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Fleet\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Yard extends Model
{
    use BelongsToOperatingCompany, SoftDeletes;

    protected $fillable = [
        'operating_company_id',
        'nome',
        'cidade',
        'endereco',
        'telefone',
        'ativo',
        'principal',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'principal' => 'boolean',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function displayLabel(): string
    {
        return $this->cidade
            ? "{$this->nome} — {$this->cidade}"
            : $this->nome;
    }
}
