<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\FiscalDocumentStatus;
use App\Enums\RentalPricingPeriod;
use App\Enums\UserRole;
use App\Livewire\Fiscal\FiscalDocumentIndex;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fiscal\FiscalDocument;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalBillingService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FiscalBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['fiscal.enabled' => true]);
    }

    public function test_invoice_creates_fiscal_document(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-FIS-1', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $entry = RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->first();
        app(RentalBillingService::class)->authorizeAndInvoice($entry, $user);

        $this->assertSame(1, FiscalDocument::query()->where('rental_id', $rental->id)->count());

        $doc = FiscalDocument::query()->where('rental_id', $rental->id)->first();
        $this->assertSame(FiscalDocumentStatus::Pendente->value, $doc->status);
        $this->assertNotNull($doc->erp_payload);
    }

    public function test_fiscal_index_lists_documents(): void
    {
        $user = $this->user(UserRole::Gestor);
        $this->actingAs($user);

        FiscalDocument::create([
            'codigo' => 'FIS-26060001',
            'tipo' => 'nfse',
            'status' => FiscalDocumentStatus::Pendente->value,
            'valor' => 100,
            'descricao' => 'Teste NFS-e',
            'erp_provider' => 'omie',
        ]);

        Livewire::test(FiscalDocumentIndex::class)
            ->assertSee('FIS-26060001')
            ->assertSee('Pendente no ERP');
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Fiscal',
            'cpf_cnpj' => '12345678901',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Fiscal',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);

        $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
