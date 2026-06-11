<?php

namespace App\Support;

use App\Models\Domain\Organization\OperatingCompany;

final class BrandContext
{
    public static function appName(): string
    {
        return (string) config('app.name', 'Gestão Acesso');
    }

    public static function activeCompanyName(): string
    {
        return self::currentCompany()?->nome ?? self::appName();
    }

    public static function currentCompany(): ?OperatingCompany
    {
        return ActiveOperatingCompany::current();
    }

    /** @return array<string, string|null> */
    public static function documentHeader(?OperatingCompany $company = null): array
    {
        $company ??= self::currentCompany();

        if ($company) {
            return $company->documentHeader();
        }

        $fallback = config('documents.company', []);

        return [
            'name' => $fallback['name'] ?? self::appName(),
            'document' => $fallback['document'] ?? '',
            'address' => $fallback['address'] ?? '',
            'phone' => $fallback['phone'] ?? '',
            'email' => $fallback['email'] ?? '',
            'logo_path' => $fallback['logo_path'] ?? null,
        ];
    }

    public static function exportTitle(string $reportName): string
    {
        return $reportName.' — '.self::activeCompanyName();
    }

    public static function documentFooter(?array $company = null): string
    {
        return $company['name'] ?? self::activeCompanyName();
    }
}
