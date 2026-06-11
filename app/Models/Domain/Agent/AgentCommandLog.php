<?php

namespace App\Models\Domain\Agent;

use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommandLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_session_id',
        'user_id',
        'operating_company_id',
        'command',
        'input',
        'result',
        'dry_run',
        'ok',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
            'dry_run' => 'boolean',
            'ok' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operatingCompany(): BelongsTo
    {
        return $this->belongsTo(OperatingCompany::class);
    }
}
