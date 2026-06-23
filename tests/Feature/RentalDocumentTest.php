<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Services\AssetStatusService;
use App\Services\DocumentPdfService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_rental_contract_pdf_includes_prorata_clause_when_enabled(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();
        $rental->update(['contrato_clausula_prorata' => true]);

        $this->actingAs($admin);

        $html = $this->renderContractHtml($rental->fresh());

        $this->assertStringContainsString('Prorrogação automática e pro-rata', $html);
    }

    public function test_rental_contract_pdf_omits_prorata_clause_when_disabled(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();
        $rental->update(['contrato_clausula_prorata' => false]);

        $this->actingAs($admin);

        $html = $this->renderContractHtml($rental->fresh());

        $this->assertStringNotContainsString('Prorrogação automática e pro-rata', $html);
    }

    public function test_rental_statement_pdf_download(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();

        $this->actingAs($admin)
            ->get(route('rentals.statement.pdf', [
                'rental' => $rental,
                'de' => now()->toDateString(),
                'ate' => now()->addDays(7)->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_rental_summary_pdf_omits_horimetro_warning_for_category_without_horimetro(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();
        $rental->asset->equipmentModel->category->update(['usa_horimetro' => false]);

        $this->actingAs($admin);

        $html = $this->renderSummaryHtml($rental->fresh());

        $this->assertStringNotContainsString('Horímetro não registrado', $html);
        $this->assertStringNotContainsString('Horímetro atual', $html);
    }

    private function renderContractHtml(Rental $rental): string
    {
        $rental->load(['asset.equipmentModel.category', 'customer', 'commercialUser']);

        $clauses = config('documents.rental_contract_clauses', []);
        if ($rental->contrato_clausula_prorata) {
            $clauses[] = config('documents.rental_contract_prorata_clause');
        }

        return view('documents.rental-contract', [
            'rental' => $rental,
            'generatedAt' => now(),
            'clauses' => $clauses,
            'company' => config('documents.company'),
            'documentTitle' => 'Contrato',
            'logoBase64' => null,
        ])->render();
    }

    private function renderSummaryHtml(Rental $rental): string
    {
        $rental->load(['asset.equipmentModel.category', 'customer']);

        return view('documents.rental-summary', [
            'rental' => $rental,
            'generatedAt' => now(),
            'fichaWarnings' => \App\Support\FichaCompleteness::rentalWarnings($rental),
            'fichaComplete' => \App\Support\FichaCompleteness::isRentalComplete($rental),
            'customFieldRows' => [],
            'company' => config('documents.company'),
            'documentTitle' => 'Resumo',
            'logoBase64' => null,
        ])->render();
    }

    private function adminUser()
    {
        $user = \App\Models\User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function createReservedRental(): Rental
    {
        $this->actingAs($this->adminUser());

        $category = EquipmentCategory::create([
            'nome' => 'PDF',
            'tipo_linha' => 'linha_leve',
            'usa_horimetro' => true,
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);

        $asset = app(AssetStatusService::class)->createWithInitialStatus(
            new Asset([
                'codigo_patrimonio' => 'PAT-PDF-1',
                'equipment_model_id' => $model->id,
                'localizacao' => 'Pátio',
            ]),
            AssetStatus::Disponivel,
        );

        $customer = Customer::create([
            'nome' => 'Cliente PDF',
            'cpf_cnpj' => '52998224725',
            'telefone' => '11999999999',
            'endereco' => 'Rua PDF',
            'contato' => 'João',
            'ativo' => true,
        ]);

        return app(RentalService::class)->reserve($asset, $customer);
    }
}
