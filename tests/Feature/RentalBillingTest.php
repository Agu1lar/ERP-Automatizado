<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\RentalBillingQueueStatus;
use App\Enums\RentalBillingQueueType;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Finance\BillingQueueIndex;
use App\Livewire\Rental\RentalShow;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\Domain\Rental\RentalItem;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalBillingService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class RentalBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checkout_creates_billing_queue_entry_and_rental_item(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-1', AssetStatus::Disponivel);

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

        $this->assertSame(1, ReceivableTitle::query()->where('rental_id', $rental->id)->count());
        $this->assertSame(1, RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->count());
        $this->assertSame(1, RentalItem::query()->where('rental_id', $rental->id)->where('ativo', true)->count());

        $entry = RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->first();
        $this->assertSame(RentalBillingQueueType::Locacao->value, $entry->tipo);
        $this->assertSame(RentalBillingQueueStatus::Pendente->value, $entry->status);
        $this->assertSame('600.00', $entry->valor_car);
        $this->assertNotNull($entry->receivable_title_id);
        $this->assertSame('aberto', $entry->receivableTitle->status);
    }

    public function test_checkout_with_auto_invoice_creates_receivable_title(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-AUTO', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));

        Livewire::test(RentalShow::class, ['rental' => $rental])
            ->call('openCheckoutModal')
            ->set('checklistItems', array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true))
            ->set('gerar_fatura_na_saida', true)
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertSet('activeTab', 'faturamento');

        $this->assertSame(1, ReceivableTitle::query()->where('rental_id', $rental->id)->count());
        $entry = RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->first();
        $this->assertSame(RentalBillingQueueStatus::Faturado->value, $entry->status);
    }

    public function test_invoice_pending_billing_from_workflow(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-WF', AssetStatus::Disponivel);

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

        Livewire::test(RentalShow::class, ['rental' => $rental])
            ->call('invoicePendingBilling')
            ->assertHasNoErrors()
            ->assertSet('activeTab', 'faturamento');

        $this->assertSame(1, ReceivableTitle::query()->where('rental_id', $rental->id)->count());
    }

    public function test_invoice_billing_entry_creates_receivable_title(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-2', AssetStatus::Disponivel);

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

        $entry = RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->firstOrFail();
        app(RentalBillingService::class)->authorizeAndInvoice($entry);

        $this->assertSame(1, ReceivableTitle::query()->where('rental_id', $rental->id)->count());
        $entry->refresh();
        $this->assertSame(RentalBillingQueueStatus::Faturado->value, $entry->status);
        $this->assertNotNull($entry->receivable_title_id);
    }

    public function test_return_marks_rental_items_as_returned(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-3', AssetStatus::Disponivel);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(3));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $rental = app(RentalService::class)->registerReturn(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true),
        );

        $item = RentalItem::query()->where('rental_id', $rental->id)->where('ativo', true)->first();
        $this->assertTrue($item->devolvido);
        $this->assertNotNull($item->devolvido_em);
    }

    public function test_billing_queue_page_loads_for_gestor(): void
    {
        $gestor = $this->user(UserRole::Gestor);

        $this->actingAs($gestor)
            ->get(route('finance.billing-queue'))
            ->assertOk();

        Livewire::actingAs($gestor)
            ->test(BillingQueueIndex::class)
            ->assertOk();
    }

    public function test_rental_show_faturamento_tab_actions(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-4', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 50,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(2));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        Livewire::actingAs($user)
            ->test(RentalShow::class, ['rental' => $rental])
            ->set('activeTab', 'faturamento')
            ->call('invoiceBillingEntry', RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->value('id'))
            ->assertHasNoErrors();

        $this->assertSame(1, ReceivableTitle::query()->where('rental_id', $rental->id)->count());
    }

    public function test_renewal_command_creates_queue_entry_when_due(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-5', AssetStatus::Disponivel);

        $this->actingAs($user);

        $rental = Rental::create([
            'codigo' => 'LOC-RENEW-1',
            'customer_id' => $customer->id,
            'asset_id' => $asset->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(30),
            'checkout_at' => now()->subDays(30),
            'billing_cycle_days' => 28,
            'billing_period_start' => now()->subDays(30),
            'billing_period_end' => now()->subDays(3),
            'next_billing_at' => now()->subDay(),
            'valor_faturamento' => 800,
        ]);

        RentalItem::create([
            'rental_id' => $rental->id,
            'asset_id' => $asset->id,
            'descricao' => 'Teste',
            'quantidade' => 1,
            'valor_locacao' => 800,
            'ativo' => true,
        ]);

        $this->artisan('rentals:process-billing-renewals')
            ->assertSuccessful();

        $this->assertSame(1, RentalBillingQueueEntry::query()
            ->where('rental_id', $rental->id)
            ->where('tipo', RentalBillingQueueType::Renovacao->value)
            ->count());
    }

    public function test_billing_and_receivable_exports_are_downloadable(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-EXP', AssetStatus::Disponivel);

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

        $entry = RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->firstOrFail();
        app(RentalBillingService::class)->authorizeAndInvoice($entry);

        $entry = $entry->fresh();
        $title = ReceivableTitle::query()->where('rental_id', $rental->id)->firstOrFail();

        $pdfResponse = $this->get(route('finance.billing.pdf', $entry));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('.pdf', (string) $pdfResponse->headers->get('content-disposition'));

        $this->get(route('finance.billing.export', $entry))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->get(route('finance.receivable.export', $title))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_invoice_from_queue_sets_highlight_and_dispatches_download(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-DL', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 80,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(4));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $entryId = RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->value('id');

        Livewire::actingAs($user)
            ->test(BillingQueueIndex::class)
            ->call('invoiceEntry', $entryId)
            ->assertDispatched('billing-download')
            ->assertSet('highlightBillingEntryId', $entryId)
            ->assertSet('statusFilter', RentalBillingQueueStatus::Faturado->value);
    }

    public function test_authorize_entry_switches_to_autorizado_filter(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BILL-AUTH', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 80,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(4));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $entryId = RentalBillingQueueEntry::query()->where('rental_id', $rental->id)->value('id');

        Livewire::actingAs($user)
            ->test(BillingQueueIndex::class)
            ->call('authorizeEntry', $entryId)
            ->assertSet('statusFilter', RentalBillingQueueStatus::Autorizado->value)
            ->assertSet('highlightBillingEntryId', $entryId)
            ->assertSee('Gerar fatura agora');
    }

    public function test_substitution_preserves_contract_rate_on_renewal(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();

        $category = EquipmentCategory::create([
            'nome' => 'Martelete',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model5kg = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => '5kg',
            'ativo' => true,
        ]);

        $model10kg = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => '10kg',
            'ativo' => true,
        ]);

        EquipmentPricing::create([
            'equipment_model_id' => $model5kg->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        EquipmentPricing::create([
            'equipment_model_id' => $model10kg->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 250,
            'ativo' => true,
        ]);

        $original = app(AssetStatusService::class)->createWithInitialStatus(new Asset([
            'codigo_patrimonio' => 'PAT-5KG',
            'equipment_model_id' => $model5kg->id,
            'localizacao' => 'Pátio',
            'horimetro' => 120.5,
        ]), AssetStatus::Disponivel);

        $replacement = app(AssetStatusService::class)->createWithInitialStatus(new Asset([
            'codigo_patrimonio' => 'PAT-10KG',
            'equipment_model_id' => $model10kg->id,
            'localizacao' => 'Pátio',
            'horimetro' => 45.0,
        ]), AssetStatus::Disponivel);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($original, $customer, now()->addDays(30));
        $rental->update(['valor_faturamento' => 500]);
        $rental = app(RentalService::class)->checkout(
            $rental->fresh(),
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $item = RentalItem::query()->where('rental_id', $rental->id)->where('ativo', true)->first();
        $this->assertSame('500.00', $item->valor_contratado);

        $rental = app(RentalService::class)->substituteAsset($rental, $replacement, 'Troca por indisponibilidade');

        $newItem = RentalItem::query()->where('rental_id', $rental->id)->where('ativo', true)->first();
        $this->assertSame('500.00', $newItem->valor_contratado);
        $this->assertSame('45.00', $newItem->horimetro_entrada);

        $oldItem = RentalItem::query()->where('rental_id', $rental->id)->where('ativo', false)->first();
        $this->assertSame('120.50', $oldItem->horimetro_saida);

        $rental->update([
            'next_billing_at' => now()->subDay(),
            'billing_period_end' => now()->subDays(2),
        ]);

        app(RentalBillingService::class)->createRenewalIfDue($rental->fresh());

        $renewal = RentalBillingQueueEntry::query()
            ->where('rental_id', $rental->id)
            ->where('tipo', RentalBillingQueueType::Renovacao->value)
            ->first();

        $this->assertNotNull($renewal);
        $this->assertSame('500.00', $renewal->valor_car);
    }

    public function test_checkout_queues_frete_entrega_as_separate_billing_entry(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-FRETE-1', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));
        $rental->update([
            'valor_faturamento' => 400,
            'valor_frete_entrega' => 85,
        ]);

        $rental = app(RentalService::class)->checkout(
            $rental->fresh(),
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $locacao = RentalBillingQueueEntry::query()
            ->where('rental_id', $rental->id)
            ->where('tipo', RentalBillingQueueType::Locacao->value)
            ->first();
        $frete = RentalBillingQueueEntry::query()
            ->where('rental_id', $rental->id)
            ->where('tipo', RentalBillingQueueType::FreteEntrega->value)
            ->first();

        $this->assertNotNull($locacao);
        $this->assertSame('400.00', $locacao->valor_car);
        $this->assertNotNull($frete);
        $this->assertSame('85.00', $frete->valor_car);
        $this->assertSame(2, ReceivableTitle::query()->where('rental_id', $rental->id)->count());
    }

    public function test_return_queues_frete_recolhida(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $asset = $this->asset('PAT-FRETE-2', AssetStatus::Disponivel);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(3));
        $rental->update(['valor_frete_recolhida' => 120]);
        $rental = app(RentalService::class)->checkout(
            $rental->fresh(),
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        app(RentalService::class)->registerReturn(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true),
        );

        $entry = RentalBillingQueueEntry::query()
            ->where('rental_id', $rental->id)
            ->where('tipo', RentalBillingQueueType::FreteRecolhida->value)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('120.00', $entry->valor_car);
        $this->assertSame(RentalBillingQueueStatus::Pendente->value, $entry->status);
    }

    public function test_bulk_authorize_selected_entries(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-BULK-1', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 50,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental1 = app(RentalService::class)->reserve($asset, $customer, now()->addDays(2));
        $rental1 = app(RentalService::class)->checkout(
            $rental1,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $asset2 = $this->asset('PAT-BULK-2', AssetStatus::Disponivel);
        EquipmentPricing::create([
            'equipment_model_id' => $asset2->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 50,
            'ativo' => true,
        ]);

        $rental2 = app(RentalService::class)->reserve($asset2, $customer, now()->addDays(2));
        $rental2 = app(RentalService::class)->checkout(
            $rental2,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $ids = RentalBillingQueueEntry::query()
            ->whereIn('rental_id', [$rental1->id, $rental2->id])
            ->pluck('id')
            ->all();

        Livewire::actingAs($user)
            ->test(BillingQueueIndex::class)
            ->set('selectedEntryIds', $ids)
            ->call('authorizeSelected')
            ->assertSet('statusFilter', RentalBillingQueueStatus::Autorizado->value);

        $this->assertSame(2, RentalBillingQueueEntry::query()
            ->whereIn('id', $ids)
            ->where('status', RentalBillingQueueStatus::Autorizado->value)
            ->count());
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Billing',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Billing',
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
