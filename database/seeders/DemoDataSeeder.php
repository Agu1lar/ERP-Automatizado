<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Jobs\GenerateAssetQrCodeJob;
use App\Services\AssetStatusService;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $category = EquipmentCategory::firstOrCreate(
            ['nome' => 'Martelete'],
            ['tipo_linha' => 'linha_leve', 'ativo' => true],
        );

        $model = EquipmentModel::firstOrCreate(
            [
                'equipment_category_id' => $category->id,
                'marca' => 'Bosch',
                'modelo' => 'GBH 2-24',
            ],
            [
                'ativo' => true,
                'especificacoes' => ['potencia' => '790W', 'peso' => '2.8kg'],
            ],
        );

        if (! Asset::where('codigo_patrimonio', 'PAT-DEMO-001')->exists()) {
            $asset = new Asset([
                'codigo_patrimonio' => 'PAT-DEMO-001',
                'equipment_model_id' => $model->id,
                'serie' => 'SN-2024-001',
                'valor_compra' => 1250.00,
                'data_compra' => now()->subMonths(6)->toDateString(),
                'localizacao' => 'Pátio A',
                'observacoes' => 'Patrimônio de demonstração para validação do fluxo.',
            ]);

            $asset = app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
            GenerateAssetQrCodeJob::dispatch($asset->id);
        }

        Customer::firstOrCreate(
            ['cpf_cnpj' => '12345678909'],
            [
                'nome' => 'Cliente Demo Ltda',
                'telefone' => '(11) 99999-0000',
                'email' => 'demo@cliente.com',
                'ativo' => true,
            ],
        );
    }
}
