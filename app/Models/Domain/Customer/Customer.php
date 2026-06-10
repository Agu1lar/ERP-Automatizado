<?php

namespace App\Models\Domain\Customer;

use App\Enums\RentalStatus;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nome',
        'cpf_cnpj',
        'contato',
        'telefone',
        'email',
        'endereco',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class)->latest();
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class)->latest('opened_at');
    }

    public function activeRentals(): HasMany
    {
        return $this->rentals()->active();
    }

    public function totalRevenue(): float
    {
        return (float) $this->rentals()
            ->where('status', RentalStatus::Concluido->value)
            ->sum('valor_faturamento');
    }

    public function formattedDocument(): string
    {
        $doc = preg_replace('/\D/', '', $this->cpf_cnpj);

        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }

        if (strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }

        return $this->cpf_cnpj;
    }
}
