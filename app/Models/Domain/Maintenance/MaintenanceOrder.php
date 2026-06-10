<?php

namespace App\Models\Domain\Maintenance;

use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\MaintenancePriority;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceOrder extends Model
{
    protected $fillable = [
        'codigo',
        'asset_id',
        'rental_id',
        'customer_id',
        'preventive_rule_id',
        'horimetro_servico',
        'status',
        'tipo',
        'prioridade',
        'impeditiva',
        'descricao_problema',
        'diagnostico',
        'solucao_aplicada',
        'parecer_tecnico',
        'assinatura_caixa',
        'assinatura_orcado_por',
        'assinatura_montado_por',
        'observacoes',
        'opened_at',
        'opened_by',
        'assigned_to',
        'started_at',
        'expected_completion_at',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'impeditiva' => 'boolean',
            'opened_at' => 'datetime',
            'started_at' => 'datetime',
            'expected_completion_at' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'horimetro_servico' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function preventiveRule(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenanceRule::class, 'preventive_rule_id');
    }

    public function resolvedCustomer(): ?Customer
    {
        return $this->customer ?? $this->rental?->customer;
    }

    public function parecerTecnicoText(): string
    {
        if (filled($this->parecer_tecnico)) {
            return $this->parecer_tecnico;
        }

        return trim(implode("\n\n", array_filter([
            filled($this->diagnostico) ? "Diagnóstico:\n{$this->diagnostico}" : null,
            filled($this->solucao_aplicada) ? "Solução:\n{$this->solucao_aplicada}" : null,
        ])));
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MaintenancePart::class);
    }

    public function laborHours(): HasMany
    {
        return $this->hasMany(MaintenanceLaborHour::class);
    }

    public function statusEnum(): MaintenanceOrderStatus
    {
        return MaintenanceOrderStatus::from($this->status);
    }

    public function tipoEnum(): MaintenanceOrderType
    {
        return MaintenanceOrderType::from($this->tipo);
    }

    public function prioridadeEnum(): MaintenancePriority
    {
        return MaintenancePriority::from($this->prioridade);
    }

    public function isBlocking(): bool
    {
        return $this->impeditiva && $this->statusEnum()->isOpen();
    }

    public function totalPartsCost(): float
    {
        return (float) $this->parts->sum(
            fn (MaintenancePart $part) => ($part->valor_unitario ?? 0) * $part->quantidade
        );
    }

    public function totalLaborHours(): float
    {
        return (float) $this->laborHours->sum('horas');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            MaintenanceOrderStatus::Aberta->value,
            MaintenanceOrderStatus::EmExecucao->value,
            MaintenanceOrderStatus::AguardandoPeca->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('expected_completion_at')
            ->whereDate('expected_completion_at', '<', now()->toDateString());
    }
}
