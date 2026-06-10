<?php

namespace App\Support;

use App\Enums\CustomFieldEntity;
use App\Enums\RentalStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Services\CustomFieldService;

class FichaCompleteness
{
    /** @return list<array{field: string, message: string}> */
    public static function assetWarnings(Asset $asset): array
    {
        $warnings = [];

        if (blank($asset->descricao)) {
            $warnings[] = ['field' => 'descricao', 'message' => 'Descrição do equipamento não preenchida'];
        }

        if ($asset->horimetro === null) {
            $warnings[] = ['field' => 'horimetro', 'message' => 'Horímetro não registrado'];
        }

        if (blank($asset->serie)) {
            $warnings[] = ['field' => 'serie', 'message' => 'Número de série não informado'];
        }

        return array_merge($warnings, app(CustomFieldService::class)->warningsFor(
            CustomFieldEntity::Asset,
            $asset->id,
        ));
    }

    /** @return list<array{field: string, message: string}> */
    public static function customerWarnings(Customer $customer): array
    {
        $warnings = [];

        if (blank($customer->telefone) && blank($customer->email)) {
            $warnings[] = ['field' => 'contato', 'message' => 'Cliente sem telefone ou e-mail'];
        }

        if (blank($customer->endereco)) {
            $warnings[] = ['field' => 'endereco', 'message' => 'Endereço do cliente não informado'];
        }

        if (blank($customer->contato)) {
            $warnings[] = ['field' => 'contato_nome', 'message' => 'Nome do contato não informado'];
        }

        return $warnings;
    }

    /** @return list<array{field: string, message: string}> */
    public static function rentalWarnings(Rental $rental): array
    {
        $rental->loadMissing(['asset.equipmentModel', 'customer']);

        $warnings = self::assetWarnings($rental->asset);
        $warnings = array_merge($warnings, self::customerWarnings($rental->customer));

        $status = $rental->statusEnum();

        if (in_array($status, [RentalStatus::Locado, RentalStatus::EmInspecao, RentalStatus::Concluido], true)
            && $rental->horimetro_saida === null) {
            $warnings[] = ['field' => 'horimetro_saida', 'message' => 'Horímetro de saída não registrado'];
        }

        if (in_array($status, [RentalStatus::Reservado, RentalStatus::Locado], true)
            && blank($rental->local_obra)) {
            $warnings[] = ['field' => 'local_obra', 'message' => 'Local da obra não informado'];
        }

        if (in_array($status, [RentalStatus::EmInspecao, RentalStatus::Concluido], true)
            && $rental->horimetro_retorno === null) {
            $warnings[] = ['field' => 'horimetro_retorno', 'message' => 'Horímetro de retorno não registrado'];
        }

        $warnings = array_merge($warnings, app(CustomFieldService::class)->warningsFor(
            CustomFieldEntity::Rental,
            $rental->id,
        ));

        return self::uniqueByField($warnings);
    }

    /** @return list<array{field: string, message: string}> */
    public static function maintenanceOrderWarnings(MaintenanceOrder $order): array
    {
        return app(CustomFieldService::class)->warningsFor(
            CustomFieldEntity::MaintenanceOrder,
            $order->id,
        );
    }

    public static function isAssetComplete(Asset $asset): bool
    {
        return self::assetWarnings($asset) === [];
    }

    public static function isRentalComplete(Rental $rental): bool
    {
        return self::rentalWarnings($rental) === [];
    }

    /** @param list<array{field: string, message: string}> $warnings */
    public static function hasFieldWarning(array $warnings, string $field): bool
    {
        foreach ($warnings as $warning) {
            if ($warning['field'] === $field) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{field: string, message: string}> $warnings */
    private static function uniqueByField(array $warnings): array
    {
        $seen = [];
        $result = [];

        foreach ($warnings as $warning) {
            if (isset($seen[$warning['field']])) {
                continue;
            }

            $seen[$warning['field']] = true;
            $result[] = $warning;
        }

        return $result;
    }
}
