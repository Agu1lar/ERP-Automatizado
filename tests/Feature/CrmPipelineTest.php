<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\OpportunityStage;
use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalQuoteStatus;
use App\Enums\UserRole;
use App\Livewire\Crm\CommercialPipelineIndex;
use App\Livewire\Crm\InactiveCustomersIndex;
use App\Livewire\Customer\CustomerShow;
use App\Models\Domain\Crm\CommercialOpportunity;
use App\Models\Domain\Crm\OutboundMessage;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\User;
use App\Services\CrmCampaignService;
use App\Services\OutboundMessagingService;
use App\Services\RentalQuoteService;
use App\Services\RentalService;
use App\Services\AssetStatusService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_quote_syncs_opportunity_through_pipeline_stages(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer('Cliente CRM', '39053344705');
        $asset = $this->asset('PAT-CRM-1');

        EquipmentPricing::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $quote = app(RentalQuoteService::class)->create($asset, $customer, now()->addDays(3));

        $opp = CommercialOpportunity::query()->where('rental_quote_id', $quote->id)->first();
        $this->assertNotNull($opp);
        $this->assertSame(OpportunityStage::Proposta->value, $opp->stage);

        $quote = app(RentalQuoteService::class)->send($quote, 7);
        $this->assertSame(OpportunityStage::Negociacao->value, $opp->fresh()->stage);

        app(RentalQuoteService::class)->convertToReservation($quote);
        $this->assertSame(OpportunityStage::Ganho->value, $opp->fresh()->stage);
        $this->assertNotNull($opp->fresh()->won_at);
    }

    public function test_pipeline_page_loads_and_creates_lead(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer('Lead Test', '52998224725');

        $this->actingAs($user);

        Livewire::test(CommercialPipelineIndex::class)
            ->assertSee('Pipeline comercial')
            ->set('showLeadForm', true)
            ->set('customer_id', $customer->id)
            ->set('titulo', 'Obra nova região sul')
            ->call('saveLead')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('commercial_opportunities', [
            'customer_id' => $customer->id,
            'titulo' => 'Obra nova região sul',
            'stage' => OpportunityStage::Lead->value,
        ]);
    }

    public function test_inactive_campaign_queues_whatsapp_messages(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer('Inativo CRM', '11144477735', '31999887766');

        $this->actingAs($user);

        $count = app(CrmCampaignService::class)->queueInactiveCampaign(
            OutboundChannel::Whatsapp,
            6,
            [$customer->id],
        );

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('outbound_messages', [
            'customer_id' => $customer->id,
            'channel' => OutboundChannel::Whatsapp->value,
            'status' => OutboundMessageStatus::Pending->value,
        ]);

        $processed = app(OutboundMessagingService::class)->processPending();
        $this->assertSame(1, $processed);
        $this->assertSame(
            OutboundMessageStatus::Sent->value,
            OutboundMessage::query()->first()->fresh()->status,
        );
    }

    public function test_customer_show_logs_crm_activity(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer('Follow-up', '39053344705', '31991112222');

        $this->actingAs($user);

        Livewire::test(CustomerShow::class, ['customer' => $customer])
            ->assertSee('CRM — follow-up')
            ->set('activity_descricao', 'Ligação realizada, cliente pediu retorno.')
            ->set('activity_follow_up', now()->addDays(3)->toDateString())
            ->call('logActivity')
            ->assertHasNoErrors();

        $customer->refresh();
        $this->assertNotNull($customer->ultimo_contato_em);
        $this->assertNotNull($customer->proximo_follow_up_em);
        $this->assertDatabaseHas('commercial_activities', [
            'customer_id' => $customer->id,
            'descricao' => 'Ligação realizada, cliente pediu retorno.',
        ]);
    }

    public function test_inactive_query_excludes_recent_rentals(): void
    {
        $user = $this->user(UserRole::Comercial);
        $active = $this->customer('Ativo recente', '39053344705', '31990001111');
        $inactive = $this->customer('Sem locação', '52998224725', '31990002222');
        $asset = $this->asset('PAT-CRM-2');

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $active, now()->addDays(2));
        $rental->update(['reserved_at' => now()]);

        Livewire::test(InactiveCustomersIndex::class)
            ->assertSee('Campanha — clientes inativos')
            ->assertSee($inactive->nome)
            ->assertDontSee($active->nome);
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function customer(string $nome, string $cpf, ?string $telefone = null): Customer
    {
        return Customer::create([
            'nome' => $nome,
            'cpf_cnpj' => $cpf,
            'telefone' => $telefone,
            'ativo' => true,
        ]);
    }

    private function asset(string $code): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'CRM',
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

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
