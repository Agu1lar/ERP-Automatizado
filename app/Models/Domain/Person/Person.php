<?php

namespace App\Models\Domain\Person;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nome',
        'cpf',
        'data_nascimento',
        'telefone',
        'telefone_secundario',
        'email',
        'cargo',
        'company_id',
        'endereco_residencial',
        'endereco_comercial',
        'observacoes',
        'ativo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function formattedCpf(): string
    {
        $doc = preg_replace('/\D/', '', $this->cpf);

        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
    }

    public function primaryContact(): ?string
    {
        return $this->telefone ?: $this->telefone_secundario ?: $this->email;
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $digits = preg_replace('/\D/', '', $term);

        return $query->where(function (Builder $inner) use ($term, $digits) {
            $inner->where('nome', 'like', '%'.$term.'%')
                ->orWhere('telefone', 'like', '%'.$term.'%')
                ->orWhere('telefone_secundario', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('cargo', 'like', '%'.$term.'%')
                ->orWhere('endereco_residencial', 'like', '%'.$term.'%')
                ->orWhere('endereco_comercial', 'like', '%'.$term.'%');

            if ($digits !== '') {
                $inner->orWhere('cpf', 'like', '%'.$digits.'%');
            }

            $inner->orWhereHas('company', function (Builder $company) use ($term) {
                $company->where('nome', 'like', '%'.$term.'%')
                    ->orWhere('endereco', 'like', '%'.$term.'%');
            });
        });
    }
}
