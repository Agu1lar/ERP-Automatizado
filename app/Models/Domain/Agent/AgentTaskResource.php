<?php

namespace App\Models\Domain\Agent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTaskResource extends Model
{
    protected $fillable = [
        'agent_task_id',
        'resource_type',
        'resource_id',
        'snapshot_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_updated_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AgentTask::class, 'agent_task_id');
    }
}
