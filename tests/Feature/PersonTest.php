<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Livewire\Person\CompanyIndex;
use App\Livewire\Person\PersonIndex;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\User;
use App\Services\GlobalSearchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class PersonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_people_index_is_accessible_with_permission(): void
    {
        $user = $this->user(UserRole::Comercial);

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk();

        Livewire::actingAs($user)
            ->test(PersonIndex::class)
            ->assertOk();
    }

    public function test_person_can_be_created_with_company(): void
    {
        $user = $this->user(UserRole::Comercial);
        $company = Company::create([
            'nome' => 'Construtora BH',
            'tipo' => CompanyType::Cliente->value,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(PersonIndex::class)
            ->call('create')
            ->set('nome', 'João da Silva')
            ->set('cpf', '529.982.247-25')
            ->set('telefone', '(31) 99999-1111')
            ->set('email', 'joao@construtora.local')
            ->set('company_id', $company->id)
            ->set('endereco_residencial', 'Rua das Flores, 100, Savassi, BH')
            ->set('endereco_comercial', 'Av. Afonso Pena, 500, Centro, BH')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('people', [
            'nome' => 'João da Silva',
            'cpf' => '52998224725',
            'company_id' => $company->id,
        ]);
    }

    public function test_person_search_finds_by_name_contact_and_address(): void
    {
        $user = $this->user(UserRole::Comercial);
        $company = Company::create([
            'nome' => 'Parceira Obras',
            'tipo' => CompanyType::Externa->value,
            'endereco' => 'Contagem, MG',
            'ativo' => true,
        ]);

        Person::create([
            'nome' => 'Maria Oliveira',
            'cpf' => '39053344705',
            'telefone' => '(31) 98888-2222',
            'endereco_residencial' => 'Rua Padre Pedro Pinto, 200',
            'company_id' => $company->id,
            'ativo' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(PersonIndex::class)
            ->set('search', 'Maria')
            ->assertSee('Maria Oliveira');

        Livewire::test(PersonIndex::class)
            ->set('search', '98888')
            ->assertSee('Maria Oliveira');

        Livewire::test(PersonIndex::class)
            ->set('search', 'Padre Pedro')
            ->assertSee('Maria Oliveira');

        Livewire::test(PersonIndex::class)
            ->set('search', 'Parceira Obras')
            ->assertSee('Maria Oliveira');
    }

    public function test_person_filter_by_company_type(): void
    {
        $user = $this->user(UserRole::Comercial);

        $own = Company::create(['nome' => 'ACESSO', 'tipo' => CompanyType::Propria->value, 'ativo' => true]);
        $external = Company::create(['nome' => 'Fornecedor X', 'tipo' => CompanyType::Externa->value, 'ativo' => true]);

        Person::create([
            'nome' => 'Funcionário Interno',
            'cpf' => '39053344705',
            'company_id' => $own->id,
            'ativo' => true,
            'created_by' => $user->id,
        ]);

        Person::create([
            'nome' => 'Técnico Externo',
            'cpf' => '15350946056',
            'company_id' => $external->id,
            'ativo' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(PersonIndex::class)
            ->set('companyTypeFilter', CompanyType::Propria->value)
            ->assertSee('Funcionário Interno')
            ->assertDontSee('Técnico Externo');
    }

    public function test_company_index_can_be_created(): void
    {
        $user = $this->user(UserRole::Gestor);

        $this->actingAs($user);

        Livewire::test(CompanyIndex::class)
            ->call('create')
            ->set('nome', 'Empresa Demo')
            ->set('tipo', CompanyType::Externa->value)
            ->set('endereco', 'Rua Comercial, 50')
            ->set('contacts.0.nome', 'Ana Souza')
            ->set('contacts.0.telefone', '(31) 98888-0000')
            ->set('contacts.0.principal', true)
            ->set('emails.0.email', 'comercial@demo.local')
            ->set('emails.0.rotulo', 'Comercial')
            ->set('emails.0.principal', true)
            ->call('addEmail')
            ->set('emails.1.email', 'financeiro@demo.local')
            ->set('emails.1.rotulo', 'Financeiro')
            ->call('save')
            ->assertHasNoErrors();

        $company = Company::query()->where('nome', 'Empresa Demo')->first();

        $this->assertNotNull($company);
        $this->assertDatabaseHas('company_contacts', [
            'company_id' => $company->id,
            'nome' => 'Ana Souza',
            'principal' => 1,
        ]);
        $this->assertDatabaseHas('company_emails', [
            'company_id' => $company->id,
            'email' => 'financeiro@demo.local',
            'rotulo' => 'Financeiro',
        ]);
    }

    public function test_company_search_finds_contacts_and_emails(): void
    {
        $user = $this->user(UserRole::Gestor);
        $company = Company::create([
            'nome' => 'Parceira Tech',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        $company->contacts()->create([
            'nome' => 'Carlos Lima',
            'telefone' => '(31) 97777-4444',
            'principal' => true,
        ]);

        $company->emails()->create([
            'email' => 'suporte@parceira.local',
            'rotulo' => 'Suporte',
            'principal' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CompanyIndex::class)
            ->set('search', 'suporte@parceira')
            ->assertSee('Parceira Tech');

        Livewire::test(CompanyIndex::class)
            ->set('search', '97777')
            ->assertSee('Parceira Tech');
    }

    public function test_people_are_not_in_global_search(): void
    {
        $user = $this->user(UserRole::Comercial);

        Person::create([
            'nome' => 'Pessoa Busca Global',
            'cpf' => '52998224725',
            'telefone' => '(31) 97777-3333',
            'ativo' => true,
            'created_by' => $user->id,
        ]);

        $results = app(GlobalSearchService::class)->fullResults('Pessoa Busca Global');

        $this->assertArrayNotHasKey('people', $results);
        $this->assertTrue($results['customers']->isEmpty());
        $this->assertTrue($results['assets']->isEmpty());
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }
}
