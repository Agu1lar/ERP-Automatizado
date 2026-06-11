<?php

namespace App\Models\Domain\Person;

use App\Enums\CompanyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa no cadastro CRM (fornecedores, parceiros, contatos comerciais).
 *
 * Não confundir com {@see \App\Models\Domain\Organization\OperatingCompany}
 * (CNPJ operacional / multi-empresa) nem com {@see \App\Models\Domain\Customer\Customer}.
 */
class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nome',
        'cnpj',
        'tipo',
        'endereco',
        'observacoes',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class)->orderBy('nome');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class)->orderByDesc('principal')->orderBy('nome');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(CompanyEmail::class)->orderByDesc('principal')->orderBy('email');
    }

    public function typeEnum(): CompanyType
    {
        return CompanyType::from($this->tipo);
    }

    public function formattedCnpj(): ?string
    {
        if (blank($this->cnpj)) {
            return null;
        }

        $doc = preg_replace('/\D/', '', $this->cnpj);

        if (strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }

        return $this->cnpj;
    }

    public function contactSummary(): string
    {
        $contacts = $this->relationLoaded('contacts') ? $this->contacts : $this->contacts()->get();
        $emails = $this->relationLoaded('emails') ? $this->emails : $this->emails()->get();

        $parts = [];

        if ($contacts->isNotEmpty()) {
            $parts[] = $contacts->count().' contato'.($contacts->count() > 1 ? 's' : '');
        }

        if ($emails->isNotEmpty()) {
            $parts[] = $emails->count().' e-mail'.($emails->count() > 1 ? 's' : '');
        }

        if ($parts === []) {
            return '—';
        }

        $primary = $contacts->firstWhere('principal', true) ?? $contacts->first();
        $primaryEmail = $emails->firstWhere('principal', true) ?? $emails->first();
        $detail = $primary?->displayLabel() ?? $primaryEmail?->email;

        return $detail ? implode(' · ', $parts).' — '.$detail : implode(' · ', $parts);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $digits = preg_replace('/\D/', '', $term);

        return $query->where(function (Builder $inner) use ($term, $digits) {
            $inner->where('nome', 'like', '%'.$term.'%')
                ->orWhere('endereco', 'like', '%'.$term.'%')
                ->orWhereHas('contacts', function (Builder $contacts) use ($term) {
                    $contacts->where('nome', 'like', '%'.$term.'%')
                        ->orWhere('cargo', 'like', '%'.$term.'%')
                        ->orWhere('telefone', 'like', '%'.$term.'%');
                })
                ->orWhereHas('emails', function (Builder $emails) use ($term) {
                    $emails->where('email', 'like', '%'.$term.'%')
                        ->orWhere('rotulo', 'like', '%'.$term.'%');
                });

            if ($digits !== '') {
                $inner->orWhere('cnpj', 'like', '%'.$digits.'%');
            }
        });
    }
}
