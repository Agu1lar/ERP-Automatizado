<?php

namespace Database\Seeders;

use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Organization\OperatingCompany;
use Illuminate\Database\Seeder;

class EquipmentModelSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Escavadeira' => [
                ['marca' => 'CAT', 'modelo' => '320D', 'especificacoes' => ['potencia' => '200hp']],
            ],
            'Mini Escavadeira' => [
                ['marca' => 'YANMAR', 'modelo' => 'Vio45', 'especificacoes' => ['potencia' => '50hp']],
            ],
            'Betoneira' => [
                ['marca' => 'CSM', 'modelo' => '400L', 'especificacoes' => ['capacidade' => '400L']],
            ],
            'Martelete' => [
                ['marca' => 'BOSCH', 'modelo' => 'GBH 2-26', 'especificacoes' => ['peso' => '2.7kg']],
            ],
        ];

        foreach (OperatingCompany::query()->where('ativo', true)->orderBy('id')->get() as $company) {
            foreach ($catalog as $categoryName => $models) {
                $category = EquipmentCategory::withoutGlobalScope('operating_company')
                    ->where('operating_company_id', $company->id)
                    ->where('nome', $categoryName)
                    ->first();

                if (! $category) {
                    continue;
                }

                foreach ($models as $model) {
                    EquipmentModel::withoutGlobalScope('operating_company')->firstOrCreate([
                        'equipment_category_id' => $category->id,
                        'marca' => $model['marca'],
                        'modelo' => $model['modelo'],
                        'operating_company_id' => $company->id,
                    ], [
                        'especificacoes' => $model['especificacoes'],
                        'ativo' => true,
                    ]);
                }
            }
        }
    }
}
