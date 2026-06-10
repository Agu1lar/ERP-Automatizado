<?php

namespace App\Models\Domain\Fleet;

use App\Enums\AssetStatus;
use App\Enums\QrCodeStatus;
use App\Enums\RentalStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Models\Domain\Attachment\Attachment;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'codigo_patrimonio',
        'equipment_model_id',
        'serie',
        'voltagem',
        'descricao',
        'horimetro',
        'valor_compra',
        'data_compra',
        'status',
        'localizacao',
        'observacoes',
        'motivo_bloqueio',
        'qr_code_path',
        'qr_code_status',
        'qr_code_generated_at',
        'qr_code_error',
    ];

    protected function casts(): array
    {
        return [
            'horimetro' => 'decimal:2',
            'valor_compra' => 'decimal:2',
            'data_compra' => 'date',
            'qr_code_generated_at' => 'datetime',
        ];
    }

    public function equipmentModel(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class)->latest();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class)->latest();
    }

    public function activeRental(): ?Rental
    {
        return $this->rentals()
            ->whereIn('status', [
                RentalStatus::Reservado->value,
                RentalStatus::Locado->value,
                RentalStatus::EmInspecao->value,
            ])
            ->first();
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class)->latest();
    }

    public function activeMaintenanceOrder(): ?MaintenanceOrder
    {
        return $this->maintenanceOrders()
            ->whereIn('status', [
                MaintenanceOrderStatus::Aberta->value,
                MaintenanceOrderStatus::EmExecucao->value,
                MaintenanceOrderStatus::AguardandoPeca->value,
            ])
            ->first();
    }

    public function statusEnum(): AssetStatus
    {
        return AssetStatus::from($this->status);
    }

    public function isAvailableForRental(): bool
    {
        return $this->statusEnum() === AssetStatus::Disponivel;
    }

    public function qrCodeStatusEnum(): QrCodeStatus
    {
        return QrCodeStatus::from($this->qr_code_status ?? QrCodeStatus::Pending->value);
    }
}
