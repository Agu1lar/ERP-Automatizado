<?php

namespace Database\Seeders;

use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Organization\OperatingCompany;
use Illuminate\Database\Seeder;

class EquipmentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['nome' => 'Escavadeira', 'tipo_linha' => 'pesada'],
            ['nome' => 'Mini Escavadeira', 'tipo_linha' => 'leve'],
            ['nome' => 'Betoneira', 'tipo_linha' => 'linha_leve'],
            ['nome' => 'Martelete', 'tipo_linha' => 'linha_leve'],
        ];

        foreach (OperatingCompany::query()->where('ativo', true)->orderBy('id')->get() as $company) {
            foreach ($categories as $category) {
                EquipmentCategory::withoutGlobalScope('operating_company')->firstOrCreate([
                    'nome' => $category['nome'],
                    'operating_company_id' => $company->id,
                ], [
                    'tipo_linha' => $category['tipo_linha'],
                    'ativo' => true,
                ]);
            }
        }
    }
}
