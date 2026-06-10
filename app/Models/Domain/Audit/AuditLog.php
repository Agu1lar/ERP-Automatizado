<?php

namespace App\Models\Domain\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'entidade',
        'entidade_id',
        'acao',
        'antes_json',
        'depois_json',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'antes_json' => 'array',
            'depois_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
