<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ArchiveService
{
    public function __construct(
        private readonly ArchiveValidator $validator,
    ) {}

    public function retentionDays(): int
    {
        return max(1, (int) config('archive.retention_days', 30));
    }

    public function archive(Model $model): void
    {
        $this->ensureSoftDeletes($model);

        if ($model->trashed()) {
            return;
        }

        $this->validator->validate($model);

        if ($this->hasAtivoColumn($model)) {
            $model->forceFill(['ativo' => false])->save();
        }

        $model->delete();
    }

    public function restore(Model $model): void
    {
        $this->ensureSoftDeletes($model);

        if (! $model->trashed()) {
            return;
        }

        $model->restore();

        if ($this->hasAtivoColumn($model)) {
            $model->forceFill(['ativo' => true])->save();
        }
    }

    /**
     * @return array{class-string<Model>, int}
     */
    public function purgeExpired(?Carbon $before = null): array
    {
        $before ??= now()->subDays($this->retentionDays());
        $purged = 0;
        $byClass = [];

        foreach (config('archive.models', []) as $class) {
            if (! class_exists($class) || ! $this->usesSoftDeletes($class)) {
                continue;
            }

            $count = (int) $class::onlyTrashed()
                ->where('deleted_at', '<=', $before)
                ->forceDelete();

            if ($count > 0) {
                $byClass[$class] = $count;
                $purged += $count;
            }
        }

        return ['total' => $purged, 'by_class' => $byClass];
    }

    private function ensureSoftDeletes(Model $model): void
    {
        if (! $this->usesSoftDeletes($model::class)) {
            throw new InvalidArgumentException('O modelo '.$model::class.' não suporta arquivamento.');
        }
    }

    /** @param class-string<Model>|Model $model */
    private function usesSoftDeletes(string|Model $model): bool
    {
        $class = is_string($model) ? $model : $model::class;

        return in_array(SoftDeletes::class, class_uses_recursive($class), true);
    }

    private function hasAtivoColumn(Model $model): bool
    {
        return array_key_exists('ativo', $model->getAttributes())
            || in_array('ativo', $model->getFillable(), true);
    }
}
