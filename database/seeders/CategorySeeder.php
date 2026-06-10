<?php

namespace Database\Seeders;

use App\Models\Domain\Fleet\EquipmentCategory;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['nome' => 'Betoneira', 'tipo_linha' => 'linha_leve'],
            ['nome' => 'Martelete', 'tipo_linha' => 'linha_leve'],
            ['nome' => 'Andaime', 'tipo_linha' => 'linha_leve'],
            ['nome' => 'Gerador', 'tipo_linha' => 'linha_leve'],
            ['nome' => 'Outros', 'tipo_linha' => 'linha_leve'],
        ];

        foreach ($categories as $category) {
            EquipmentCategory::firstOrCreate(
                ['nome' => $category['nome']],
                ['tipo_linha' => $category['tipo_linha'], 'ativo' => true],
            );
        }
    }
}
