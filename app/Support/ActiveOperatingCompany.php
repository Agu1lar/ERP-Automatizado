<?php

namespace App\Support;

use App\Models\Domain\Organization\OperatingCompany;
use Illuminate\Database\Eloquent\Builder;

class ActiveOperatingCompany
{
    public const SESSION_KEY = 'operating_company_id';

    private static ?int $runtimeId = null;

    public static function id(): ?int
    {
        if (self::$runtimeId !== null) {
            return self::$runtimeId;
        }

        $id = session(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public static function current(): ?OperatingCompany
    {
        $id = self::id();

        if ($id === null) {
            return null;
        }

        return OperatingCompany::query()->whereKey($id)->where('ativo', true)->first();
    }

    public static function set(int $companyId): void
    {
        self::$runtimeId = $companyId;

        if (app()->runningInConsole() === false) {
            session([self::SESSION_KEY => $companyId]);
        }
    }

    public static function forget(): void
    {
        self::$runtimeId = null;
        session()->forget(self::SESSION_KEY);
    }

    public static function bootstrapForUser(): void
    {
        if (self::id() !== null && self::current() !== null) {
            return;
        }

        $default = OperatingCompany::query()->where('slug', 'acesso')->where('ativo', true)->first()
            ?? OperatingCompany::query()->where('ativo', true)->orderBy('id')->first();

        if ($default) {
            self::set($default->id);
        }
    }

    public static function applyScope(Builder $query, string $column = 'operating_company_id'): void
    {
        $id = self::id();

        if ($id !== null) {
            $query->where($query->getModel()->getTable().'.'.$column, $id);
        }
    }

    public static function resolveIdForNewRecord(?int $explicit = null): ?int
    {
        return $explicit ?? self::id();
    }

    /**
     * Executa callback para cada empresa ativa (CLI, scheduler, jobs).
     *
     * @template TReturn
     *
     * @param  callable(OperatingCompany): TReturn  $callback
     * @return list<TReturn>
     */
    public static function forEach(callable $callback): array
    {
        $results = [];
        $previous = self::$runtimeId;

        $companies = OperatingCompany::query()->where('ativo', true)->orderBy('id')->get();

        if ($companies->isEmpty()) {
            return [$callback(null)];
        }

        foreach ($companies as $company) {
            self::set($company->id);
            $results[] = $callback($company);
        }

        if ($previous !== null) {
            self::set($previous);
        } else {
            self::forget();
        }

        return $results;
    }
}
