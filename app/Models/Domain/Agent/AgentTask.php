<?php

namespace App\Models\Domain\Agent;

use App\Enums\AgentTaskStatus;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgentTask extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'operating_company_id',
        'agent_session_id',
        'status',
        'title',
        'current_step',
        'total_steps',
        'steps',
        'resource_snapshots',
        'step_results',
        'error_message',
        'conflict_reason',
        'idempotency_key',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'resource_snapshots' => 'array',
            'step_results' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AgentTask $task): void {
            if ($task->uuid === null) {
                $task->uuid = (string) Str::uuid();
            }
        });
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

    public function resources(): HasMany
    {
        return $this->hasMany(AgentTaskResource::class);
    }

    public function statusEnum(): AgentTaskStatus
    {
        return AgentTaskStatus::from($this->status);
    }

    public function markRunning(): bool
    {
        $updated = static::query()
            ->whereKey($this->id)
            ->where('status', AgentTaskStatus::Queued->value)
            ->update([
                'status' => AgentTaskStatus::Running->value,
                'started_at' => now(),
            ]);

        if ($updated > 0) {
            $this->refresh();
        }

        return $updated > 0;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            AgentTaskStatus::Queued->value,
            AgentTaskStatus::Running->value,
        ], true);
    }

    public function isStaleInQueue(): bool
    {
        if ($this->status !== AgentTaskStatus::Queued->value) {
            return false;
        }

        $threshold = max(5, (int) config('agent.tasks.queued_stale_seconds', 20));

        return $this->created_at !== null
            && $this->created_at->diffInSeconds(now()) >= $threshold;
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => AgentTaskStatus::Completed->value,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => AgentTaskStatus::Failed->value,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }

    public function markConflict(string $reason): void
    {
        $this->update([
            'status' => AgentTaskStatus::Conflict->value,
            'conflict_reason' => $reason,
            'finished_at' => now(),
        ]);
    }

    public function markCancelled(): void
    {
        $this->update([
            'status' => AgentTaskStatus::Cancelled->value,
            'finished_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public function toAgentArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'status_label' => $this->statusEnum()->label(),
            'title' => $this->title,
            'current_step' => $this->current_step,
            'total_steps' => $this->total_steps,
            'progress_percent' => $this->total_steps > 0
                ? (int) round(($this->current_step / $this->total_steps) * 100)
                : 0,
            'can_cancel' => $this->isCancellable(),
            'is_stale' => $this->isStaleInQueue(),
            'error_message' => $this->error_message,
            'conflict_reason' => $this->conflict_reason,
            'step_results' => $this->step_results,
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
