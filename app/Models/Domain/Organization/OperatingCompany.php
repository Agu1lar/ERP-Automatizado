<?php

namespace App\Models\Domain\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperatingCompany extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'nome',
        'slug',
        'razao_social',
        'cnpj',
        'endereco',
        'telefone',
        'email',
        'logo_path',
        'ativo',
        'agent_daily_token_limit',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function formattedCnpj(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->cnpj);

        if (strlen($digits) !== 14) {
            return filled($this->cnpj) ? $this->cnpj : null;
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12, 2);
    }

    /** @return array<string, string|null> */
    public function documentHeader(): array
    {
        return [
            'name' => $this->razao_social ?: $this->nome,
            'document' => $this->formattedCnpj() ?? '',
            'address' => $this->endereco ?? '',
            'phone' => $this->telefone ?? '',
            'email' => $this->email ?? '',
            'logo_path' => $this->logo_path,
        ];
    }
}
