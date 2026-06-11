<?php

namespace App\Models\Domain\Agent;

use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentSession extends Model
{
    protected $fillable = [
        'user_id',
        'operating_company_id',
        'channel',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operatingCompany(): BelongsTo
    {
        return $this->belongsTo(OperatingCompany::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class);
    }

    public function commandLogs(): HasMany
    {
        return $this->hasMany(AgentCommandLog::class);
    }

    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }
}
