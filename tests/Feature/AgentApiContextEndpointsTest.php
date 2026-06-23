<?php

namespace Tests\Feature;

use App\Agent\AgentContextBuilder;
use App\Enums\AssetStatus;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Services\RentalQuoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsAgentApiFixtures;
use Tests\TestCase;

class AgentApiContextEndpointsTest extends TestCase
{
    use BuildsAgentApiFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_context_endpoints_for_asset_quote_receivable(): void
    {
        $user = $this->agentUser();
        $customer = $this->customer();
        $asset = $this->asset('PAT-CTX-API', AssetStatus::Disponivel);

        $title = ReceivableTitle::create([
            'codigo' => 'TIT-CTX-API',
            'customer_id' => $customer->id,
            'parcela' => 1,
            'total_parcelas' => 1,
            'valor' => 99,
            'vencimento' => now()->addWeek(),
            'status' => 'aberto',
        ]);

        $this->actingAs($user);
        $quote = app(RentalQuoteService::class)->create($asset, $customer, now()->addDays(3));

        Sanctum::actingAs($user);

        $builder = app(AgentContextBuilder::class);

        $this->assertSame('asset', $builder->asset($asset->fresh())['entity']);
        $this->assertSame('rental_quote', $builder->quote($quote->fresh())['entity']);
        $this->assertSame('receivable_title', $builder->receivableTitle($title->fresh())['entity']);

        $this->getJson('/api/agent/context/receivable/'.$title->codigo)
            ->assertOk()
            ->assertJsonPath('entity', 'receivable_title');
    }

    public function test_context_endpoints_for_person_company_billing_yard_logistics(): void
    {
        $user = $this->agentUser();
        Sanctum::actingAs($user);

        $person = Person::create([
            'nome' => 'Contato Contexto',
            'cpf' => '52998224725',
            'ativo' => true,
        ]);

        $company = Company::create([
            'nome' => 'Empresa Contexto',
            'cnpj' => '11222333000181',
            'tipo' => 'fornecedor',
            'ativo' => true,
        ]);

        $yard = Yard::create([
            'nome' => 'Pátio Teste',
            'cidade' => 'BH',
            'ativo' => true,
            'principal' => false,
        ]);

        $rental = $this->reservedRental();
        $entry = RentalBillingQueueEntry::create([
            'codigo' => 'FAT-CTX-API',
            'rental_id' => $rental->id,
            'customer_id' => $rental->customer_id,
            'tipo' => 'ciclo',
            'valor_car' => 100,
            'valor_nf' => 100,
            'status' => 'pendente',
            'gerado_em' => now(),
        ]);

        $builder = app(AgentContextBuilder::class);

        $this->assertSame('person', $builder->person($person->fresh())['entity']);
        $this->assertSame('company', $builder->company($company->fresh())['entity']);
        $this->assertSame('billing_entry', $builder->billingEntry($entry->fresh())['entity']);
        $this->assertSame('yard', $builder->yard($yard->fresh())['entity']);
        $this->assertSame('logistics_daily', $builder->logisticsDaily()['entity']);

        $this->getJson('/api/agent/context/logistics')
            ->assertOk()
            ->assertJsonPath('entity', 'logistics_daily');
    }
}
