<?php

namespace App\Models\Domain\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLaborHour extends Model
{
    protected $fillable = [
        'maintenance_order_id',
        'user_id',
        'data',
        'horas',
        'descricao_atividade',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'horas' => 'decimal:2',
        ];
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
