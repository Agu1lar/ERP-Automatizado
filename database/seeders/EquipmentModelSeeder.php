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
        $models = [
            ['marca' => 'CAT', 'modelo' => '320D', 'especificacoes' => ['potencia' => '200hp']],
            ['marca' => 'YANMAR', 'modelo' => 'Vio45', 'especificacoes' => ['potencia' => '50hp']],
        ];

        foreach (OperatingCompany::query()->where('ativo', true)->orderBy('id')->get() as $company) {
            $category = EquipmentCategory::withoutGlobalScope('operating_company')
                ->where('operating_company_id', $company->id)
                ->orderBy('id')
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
