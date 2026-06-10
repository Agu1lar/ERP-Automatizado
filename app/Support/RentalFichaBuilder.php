<?php

namespace App\Support;

use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;

class RentalFichaBuilder
{
    /** @return array{horimetro_saida: float|null, ficha_descricao: string|null, local_obra: string|null} */
    public static function prefillFromAsset(Asset $asset): array
    {
        $asset->loadMissing('equipmentModel.category');

        $descricao = $asset->descricao;
        if (blank($descricao)) {
            $descricao = trim(implode(' — ', array_filter([
                $asset->equipmentModel->displayName(),
                $asset->equipmentModel->category->nome ?? null,
                $asset->serie ? "Série {$asset->serie}" : null,
            ])));
        }

        return [
            'horimetro_saida' => $asset->horimetro,
            'ficha_descricao' => $descricao ?: null,
            'local_obra' => null,
        ];
    }

    public static function prefillLocalObraFromCustomer(Customer $customer): ?string
    {
        return filled($customer->endereco) ? trim($customer->endereco) : null;
    }

    /** @return array{horimetro_saida: float|null, ficha_descricao: string|null, local_obra: string|null} */
    public static function prefillForReservation(Asset $asset, Customer $customer): array
    {
        $ficha = self::prefillFromAsset($asset);
        $ficha['local_obra'] = self::prefillLocalObraFromCustomer($customer);

        return $ficha;
    }

    /** @return array<string, mixed> */
    public static function assetPreview(Asset $asset): array
    {
        $asset->loadMissing('equipmentModel.category');

        return [
            'id' => $asset->id,
            'codigo' => $asset->codigo_patrimonio,
            'modelo' => $asset->equipmentModel->displayName(),
            'categoria' => $asset->equipmentModel->category->nome,
            'serie' => $asset->serie,
            'descricao' => $asset->descricao,
            'horimetro' => $asset->horimetro,
            'localizacao' => $asset->localizacao,
            'status' => $asset->statusEnum()->label(),
            'disponivel' => $asset->isAvailableForRental(),
        ];
    }
}
