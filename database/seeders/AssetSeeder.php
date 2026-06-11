<?php

namespace Database\Seeders;

use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Organization\OperatingCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $acesso = OperatingCompany::query()->where('slug', 'acesso')->first();
        $super = OperatingCompany::query()->where('slug', 'supermaquinas')->first();

        $modelAcesso = EquipmentModel::withoutGlobalScope('operating_company')
            ->where('operating_company_id', $acesso->id)
            ->whereHas('category', fn ($q) => $q->where('nome', 'Escavadeira'))
            ->first();
        $modelBetoneiraAcesso = EquipmentModel::withoutGlobalScope('operating_company')
            ->where('operating_company_id', $acesso->id)
            ->whereHas('category', fn ($q) => $q->where('nome', 'Betoneira'))
            ->first();
        $modelSuper = EquipmentModel::withoutGlobalScope('operating_company')
            ->where('operating_company_id', $super->id)
            ->whereHas('category', fn ($q) => $q->where('nome', 'Escavadeira'))
            ->first();

        if (! $modelAcesso || ! $modelSuper) {
            return;
        }

        foreach (['AC-1001', 'AC-1002'] as $code) {
            Asset::withoutGlobalScope('operating_company')->firstOrCreate([
                'codigo_patrimonio' => $code,
            ], [
                'operating_company_id' => $acesso->id,
                'equipment_model_id' => $modelAcesso->id,
                'serie' => Str::upper(Str::random(8)),
                'status' => 'disponivel',
                'localizacao' => 'Depósito AC',
            ]);
        }

        if ($modelBetoneiraAcesso) {
            Asset::withoutGlobalScope('operating_company')->firstOrCreate([
                'codigo_patrimonio' => 'AC-1003',
            ], [
                'operating_company_id' => $acesso->id,
                'equipment_model_id' => $modelBetoneiraAcesso->id,
                'serie' => Str::upper(Str::random(8)),
                'status' => 'disponivel',
                'localizacao' => 'Depósito AC',
            ]);
        }

        foreach (['SM-2001', 'SM-2002'] as $code) {
            Asset::withoutGlobalScope('operating_company')->firstOrCreate([
                'codigo_patrimonio' => $code,
            ], [
                'operating_company_id' => $super->id,
                'equipment_model_id' => $modelSuper->id,
                'serie' => Str::upper(Str::random(8)),
                'status' => 'disponivel',
                'localizacao' => 'Pátio SM',
            ]);
        }
    }
}
