<?php

namespace App\Models\Domain\Agent;

use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLlmCall extends Model
{
    public const TYPE_CHAT_INTERPRET = 'chat_interpret';

    public const TYPE_DOCUMENT_ANALYZE = 'document_analyze';

    public const TYPE_HEURISTIC_FALLBACK = 'heuristic_fallback';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'operating_company_id',
        'agent_session_id',
        'call_type',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'success',
        'failure_reason',
        'used_fallback',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'success' => 'boolean',
            'used_fallback' => 'boolean',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }
}
