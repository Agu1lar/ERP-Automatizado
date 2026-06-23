<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\RentalStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Support\FichaCompleteness;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FichaCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_asset_warnings_when_fields_missing(): void
    {
        $asset = $this->createAsset();

        $warnings = FichaCompleteness::assetWarnings($asset);

        $this->assertFalse(FichaCompleteness::isAssetComplete($asset));
        $this->assertTrue(FichaCompleteness::hasFieldWarning($warnings, 'descricao'));
        $this->assertTrue(FichaCompleteness::hasFieldWarning($warnings, 'horimetro'));
    }

    public function test_asset_complete_when_ficha_fields_filled(): void
    {
        $asset = $this->createAsset();
        $asset->update([
            'descricao' => 'Martelete profissional',
            'horimetro' => 1250.5,
            'serie' => 'SN-001',
        ]);

        $this->assertTrue(FichaCompleteness::isAssetComplete($asset->fresh()));
    }

    public function test_customer_warning_when_no_phone_or_email(): void
    {
        $customer = Customer::create([
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '12345678909',
            'ativo' => true,
        ]);

        $warnings = FichaCompleteness::customerWarnings($customer);

        $this->assertTrue(FichaCompleteness::hasFieldWarning($warnings, 'contato'));
    }

    public function test_customer_no_contact_warning_when_phone_present(): void
    {
        $customer = Customer::create([
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '12345678909',
            'telefone' => '11999999999',
            'endereco' => 'Rua A, 1',
            'contato' => 'João',
            'ativo' => true,
        ]);

        $warnings = FichaCompleteness::customerWarnings($customer);

        $this->assertFalse(FichaCompleteness::hasFieldWarning($warnings, 'contato'));
    }

    public function test_rental_warnings_include_horimetro_saida_when_locado(): void
    {
        $asset = $this->createAsset();
        $customer = Customer::create([
            'nome' => 'Cliente',
            'cpf_cnpj' => '12345678909',
            'telefone' => '11',
            'endereco' => 'Rua',
            'contato' => 'A',
            'ativo' => true,
        ]);

        $rental = Rental::create([
            'codigo' => 'LOC-000001',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
        ]);

        $warnings = FichaCompleteness::rentalWarnings($rental);

        $this->assertTrue(FichaCompleteness::hasFieldWarning($warnings, 'horimetro_saida'));
    }

    public function test_rental_warnings_exclude_asset_horimetro_field(): void
    {
        $asset = $this->createAsset();
        $customer = Customer::create([
            'nome' => 'Cliente',
            'cpf_cnpj' => '12345678909',
            'telefone' => '11',
            'endereco' => 'Rua',
            'contato' => 'A',
            'ativo' => true,
        ]);

        $rental = Rental::create([
            'codigo' => 'LOC-000002',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'reserved_at' => now(),
        ]);

        $warnings = FichaCompleteness::rentalWarnings($rental);

        $this->assertFalse(FichaCompleteness::hasFieldWarning($warnings, 'horimetro'));
    }

    public function test_rental_warnings_skip_horimetro_when_category_does_not_use_it(): void
    {
        $category = EquipmentCategory::create([
            'nome' => 'Sem horímetro',
            'tipo_linha' => 'linha_leve',
            'usa_horimetro' => false,
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);

        $asset = Asset::create([
            'codigo_patrimonio' => 'PAT-NO-H',
            'equipment_model_id' => $model->id,
            'status' => AssetStatus::Disponivel->value,
        ]);

        $customer = Customer::create([
            'nome' => 'Cliente',
            'cpf_cnpj' => '12345678909',
            'telefone' => '11',
            'endereco' => 'Rua',
            'contato' => 'A',
            'ativo' => true,
        ]);

        $rental = Rental::create([
            'codigo' => 'LOC-000003',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
            'local_obra' => 'Obra X',
        ]);

        $warnings = FichaCompleteness::rentalWarnings($rental);

        $this->assertFalse(FichaCompleteness::hasFieldWarning($warnings, 'horimetro'));
        $this->assertFalse(FichaCompleteness::hasFieldWarning($warnings, 'horimetro_saida'));
    }

    private function createAsset(): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Martelete',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Bosch',
            'modelo' => 'GBH',
            'ativo' => true,
        ]);

        return Asset::create([
            'codigo_patrimonio' => 'PAT-'.uniqid(),
            'equipment_model_id' => $model->id,
            'status' => AssetStatus::Disponivel->value,
        ]);
    }
}
