<?php

namespace App\Support\Agent;

use Carbon\CarbonInterface;

/** @phpstan-type AgentResourceShape array{type: string, id: int|string, snapshot?: string|null} */
class AgentResourceReference
{
    /** @param  AgentResourceShape  $data */
    public static function key(array $data): string
    {
        return strtolower($data['type']).':'.$data['id'];
    }

    /** @param  AgentResourceShape  $data */
    public static function snapshotFromModel(object $model): string
    {
        $updated = $model->updated_at ?? null;

        return $updated instanceof CarbonInterface
            ? $updated->toIso8601String()
            : now()->toIso8601String();
    }
}
