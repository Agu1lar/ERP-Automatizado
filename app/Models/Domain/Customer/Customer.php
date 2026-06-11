<?php

namespace App\Models\Domain\Customer;

use App\Enums\RentalStatus;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\ReceivableTitleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente da operação de locação — quem recebe equipamentos e títulos a receber.
 *
 * Não possui credencial de login. Distinto de {@see \App\Models\Domain\Person\Person}
 * (cadastro comercial/CRM) e de {@see \App\Models\User} (funcionários).
 */
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
        'created_by',
        'limite_credito',
        'bloqueio_inadimplencia',
        'bloqueado',
        'motivo_bloqueio',
        'bloqueado_at',
        'bloqueado_by',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'limite_credito' => 'decimal:2',
            'bloqueio_inadimplencia' => 'boolean',
            'bloqueado' => 'boolean',
            'bloqueado_at' => 'datetime',
        ];
    }

    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bloqueado_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class)->latest();
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class)->latest('opened_at');
    }

    public function receivableTitles(): HasMany
    {
        return $this->hasMany(ReceivableTitle::class)->latest('vencimento');
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

    public function isManuallyBlocked(): bool
    {
        return (bool) $this->bloqueado;
    }

    public function rentalBlockReason(): ?string
    {
        if (! $this->isManuallyBlocked()) {
            return null;
        }

        return filled($this->motivo_bloqueio)
            ? $this->motivo_bloqueio
            : 'Cliente bloqueado manualmente.';
    }

    public function isBlockedForDisplay(): bool
    {
        return $this->isManuallyBlocked();
    }

    public function hasOverdueTitles(?ReceivableTitleService $finance = null): bool
    {
        $finance ??= app(ReceivableTitleService::class);

        return $finance->customerHasOverdueTitles($this);
    }

    /** @param  array{bloqueado?: bool, motivo_bloqueio?: ?string}  $data */
    public static function applyManualBlockPayload(array &$data, ?User $user = null): void
    {
        $bloqueado = (bool) ($data['bloqueado'] ?? false);

        if ($bloqueado) {
            $data['bloqueado_at'] = now();
            $data['bloqueado_by'] = $user?->id ?? auth()->id();
        } else {
            $data['motivo_bloqueio'] = null;
            $data['bloqueado_at'] = null;
            $data['bloqueado_by'] = null;
        }
    }
}
