<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\CompanyType;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalQuote;
use App\Models\User;
use App\Enums\RentalQuoteStatus;
use App\Services\PartPurchaseOrderService;
use App\Services\RentalQuoteService;
use App\Services\AssetStatusService;
use App\Services\CompanyService;
use App\Services\MaintenanceOrderService;
use App\Services\RentalService;
use App\Support\ActiveOperatingCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Volume alto para demonstração: centenas de clientes, patrimônios e locações.
 *
 * Uso (após migrate):
 *   php artisan db:seed --class=BulkDemoSeeder
 *
 * Não substitui o DatabaseSeeder padrão — rode à parte quando quiser carga massiva.
 */
class BulkDemoSeeder extends Seeder
{
    protected int $customersTarget = 250;

    protected int $companiesTarget = 45;

    protected int $peopleTarget = 120;

    protected int $assetsPerCompany = 150;

    protected int $openOrdersTotal = 20;

    protected int $completedOrdersTarget = 40;

    protected bool $compactRentals = false;

    protected string $rentalProfile = 'bulk';

    protected bool $seedSupplemental = false;

    protected int $driversPerCompany = 0;

    protected int $quotesRascunhoPerCompany = 3;

    protected int $quotesEnviadoPerCompany = 2;

    protected int $quotesExpiradoPerCompany = 1;

    protected int $purchaseOrdersPerCompany = 3;

    protected int $preventiveOrdersPerCompany = 2;

    protected int $partCatalogItemCount = 10;

    protected int $suppliersTarget = 4;

    /** @var list<array{nome: string, tipo: string, tipo_linha: string, modelos: list<array{marca: string, modelo: string}>}> */
    private const FLEET_BLUEPRINT = [
        [
            'nome' => 'Escavadeira',
            'tipo' => 'pesada',
            'tipo_linha' => 'pesada',
            'modelos' => [
                ['marca' => 'CAT', 'modelo' => '320D'],
                ['marca' => 'Komatsu', 'modelo' => 'PC200'],
                ['marca' => 'Volvo', 'modelo' => 'EC210'],
            ],
        ],
        [
            'nome' => 'Mini Escavadeira',
            'tipo' => 'leve',
            'tipo_linha' => 'leve',
            'modelos' => [
                ['marca' => 'Yanmar', 'modelo' => 'Vio45'],
                ['marca' => 'Bobcat', 'modelo' => 'E35'],
                ['marca' => 'Kubota', 'modelo' => 'U17'],
            ],
        ],
        [
            'nome' => 'Betoneira',
            'tipo' => 'leve',
            'tipo_linha' => 'leve',
            'modelos' => [
                ['marca' => 'Menegotti', 'modelo' => 'MAX 400'],
                ['marca' => 'CSM', 'modelo' => 'CSM 400'],
            ],
        ],
        [
            'nome' => 'Gerador',
            'tipo' => 'leve',
            'tipo_linha' => 'leve',
            'modelos' => [
                ['marca' => 'Honda', 'modelo' => 'EG 6500'],
                ['marca' => 'Toyama', 'modelo' => 'TG2800CXR'],
            ],
        ],
    ];

    public function run(): void
    {
        // Evita chamadas HTTP (Nominatim) durante seed — falham em VM/offline e interrompem a carga.
        config(['geocoding.enabled' => false]);

        $this->call([
            RolePermissionSeeder::class,
            OperatingCompanySeeder::class,
            AdminUserSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@acesso.local')->firstOrFail();
        $this->seedDemoUsers();
        $this->seedPartCatalog();
        $this->seedCompaniesAndPeople($admin);

        $customers = $this->seedCustomers($admin);
        $allAssets = [];

        foreach (OperatingCompany::query()->where('ativo', true)->orderBy('id')->get() as $company) {
            ActiveOperatingCompany::set($company->id);
            $assets = $this->seedFleetForCompany($company, $admin);
            $allAssets = array_merge($allAssets, $assets);
            $this->seedPricingForCompany();
            $this->seedPreventiveRules($admin);
        }

        if ($this->seedSupplemental) {
            $this->seedSupplementalModules($admin, $customers, $allAssets);
        }

        // OS antes das locações para garantir patrimônios disponíveis no funil.
        $this->seedOpenMaintenanceFunnel($admin, $allAssets);

        foreach (OperatingCompany::query()->where('ativo', true)->orderBy('id')->get() as $company) {
            ActiveOperatingCompany::set($company->id);
            $companyAssets = collect($allAssets)
                ->filter(fn (Asset $a) => (int) $a->operating_company_id === (int) $company->id)
                ->values()
                ->all();
            $this->seedRentalsForCompany($admin, $customers, $companyAssets);
            $this->applyFleetStatusMix($admin, $companyAssets);
        }

        $this->seedCompletedMaintenanceHistory($admin);

        $rentals = Rental::withoutGlobalScope('operating_company');
        $assets = Asset::withoutGlobalScope('operating_company');
        $openOrders = MaintenanceOrder::withoutGlobalScope('operating_company')->open()->count();
        $osByStatus = MaintenanceOrder::withoutGlobalScope('operating_company')
            ->open()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $rentalByStatus = $rentals->clone()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $assetByStatus = $assets->clone()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->command?->info(sprintf(
            'Demo: %d empresas CRM | %d pessoas | %d clientes | %d preços | %d patrimônios | %d locações | %d OS abertas | %d orçamentos | %d pedidos compra | %d motoristas',
            Company::count(),
            Person::count(),
            Customer::count(),
            EquipmentPricing::withoutGlobalScope('operating_company')->count(),
            $assets->count(),
            $rentals->count(),
            $openOrders,
            RentalQuote::withoutGlobalScope('operating_company')->count(),
            PartPurchaseOrder::withoutGlobalScope('operating_company')->count(),
            DeliveryDriver::withoutGlobalScope('operating_company')->count(),
        ));
        $this->command?->info('Locações: '.$rentalByStatus->map(fn ($n, $s) => "{$s}:{$n}")->implode(', '));
        $this->command?->info('Frota: '.$assetByStatus->map(fn ($n, $s) => "{$s}:{$n}")->implode(', '));
        $this->command?->info('OS abertas: '.$osByStatus->map(fn ($n, $s) => "{$s}:{$n}")->implode(', '));
    }

    private function seedDemoUsers(): void
    {
        $profiles = [
            ['email' => 'gestor@acesso.local', 'name' => 'Gestor Demo', 'role' => UserRole::Gestor],
            ['email' => 'comercial@acesso.local', 'name' => 'Comercial Demo', 'role' => UserRole::Comercial],
            ['email' => 'operacao@acesso.local', 'name' => 'Operação Demo', 'role' => UserRole::Operacao],
            ['email' => 'manutencao@acesso.local', 'name' => 'Manutenção Demo', 'role' => UserRole::Manutencao],
        ];

        foreach ($profiles as $profile) {
            $user = User::updateOrCreate(
                ['email' => $profile['email']],
                ['name' => $profile['name'], 'password' => 'Acesso@2026', 'ativo' => true, 'email_verified_at' => now()],
            );
            $user->syncRoles([$profile['role']->value]);
        }
    }

    /** @return list<Customer> */
    private function seedCustomers(User $admin): array
    {
        $existing = Customer::count();
        $customers = Customer::query()->orderBy('id')->get()->all();

        for ($i = $existing; $i < $this->customersTarget; $i++) {
            $isCnpj = $i % 3 !== 0;
            $doc = $isCnpj ? $this->generateValidCnpj($i + 1) : $this->generateValidCpf($i + 100);

            $customers[] = Customer::create([
                'nome' => ($isCnpj ? 'Construtora ' : 'Cliente ').Str::title(fake()->words(2, true)).' '.($i + 1),
                'cpf_cnpj' => $doc,
                'contato' => fake()->name(),
                'telefone' => fake()->phoneNumber(),
                'email' => 'cliente'.($i + 1).'@demo.local',
                'endereco' => fake()->streetAddress().', '.fake()->city().' — MG',
                'ativo' => true,
                'created_by' => $admin->id,
            ]);
        }

        return $customers;
    }

    /** @return list<Asset> */
    private function seedFleetForCompany(OperatingCompany $company, User $admin): array
    {
        $statusService = app(AssetStatusService::class);
        $assets = [];
        $prefix = strtoupper(substr($company->slug, 0, 2));
        $counter = 1;
        $unitsPerModel = (int) ceil($this->assetsPerCompany / (
            collect(self::FLEET_BLUEPRINT)->sum(fn ($c) => count($c['modelos']))
        ));

        foreach (self::FLEET_BLUEPRINT as $blueprint) {
            $category = EquipmentCategory::withoutGlobalScope('operating_company')->firstOrCreate(
                ['nome' => $blueprint['nome'], 'operating_company_id' => $company->id],
                ['tipo_linha' => $blueprint['tipo_linha'], 'ativo' => true],
            );

            foreach ($blueprint['modelos'] as $modelDef) {
                $model = EquipmentModel::withoutGlobalScope('operating_company')->firstOrCreate(
                    [
                        'equipment_category_id' => $category->id,
                        'marca' => $modelDef['marca'],
                        'modelo' => $modelDef['modelo'],
                        'operating_company_id' => $company->id,
                    ],
                    ['ativo' => true, 'especificacoes' => ['demo' => true]],
                );

                for ($u = 0; $u < $unitsPerModel; $u++) {
                    $code = sprintf('%s-%s-%04d', $prefix, strtoupper(substr($blueprint['nome'], 0, 3)), $counter);

                    if (Asset::withoutGlobalScope('operating_company')->where('codigo_patrimonio', $code)->exists()) {
                        $counter++;

                        continue;
                    }

                    $asset = new Asset([
                        'operating_company_id' => $company->id,
                        'codigo_patrimonio' => $code,
                        'equipment_model_id' => $model->id,
                        'serie' => 'SN-'.$company->slug.'-'.str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
                        'valor_compra' => 5000 + ($counter * 125),
                        'data_compra' => now()->subMonths(rand(2, 48))->toDateString(),
                        'localizacao' => ['Pátio A', 'Pátio B', 'Depósito', 'Oficina'][$counter % 4],
                        'descricao' => "Patrimônio demo {$code}",
                    ]);

                    $assets[] = $statusService->createWithInitialStatus($asset, AssetStatus::Disponivel, user: $admin);
                    $counter++;
                }
            }
        }

        return $assets;
    }

    private function seedCompaniesAndPeople(User $admin): void
    {
        $companyService = app(CompanyService::class);
        $types = [CompanyType::Propria, CompanyType::Externa, CompanyType::Cliente];
        $cargos = ['Engenheiro', 'Mestre de obras', 'Comprador', 'Diretor', 'Técnico', 'Administrativo'];

        $companies = Company::query()->orderBy('id')->get()->all();

        for ($i = count($companies); $i < $this->companiesTarget; $i++) {
            $type = $types[$i % count($types)];
            $company = Company::create([
                'nome' => match ($type) {
                    CompanyType::Propria => 'Filial '.Str::title(fake()->city()).' '.($i + 1),
                    CompanyType::Externa => 'Fornecedor '.Str::title(fake()->words(2, true)).' '.($i + 1),
                    CompanyType::Cliente => 'Parceiro '.Str::title(fake()->words(2, true)).' '.($i + 1),
                },
                'cnpj' => $i % 4 !== 0 ? $this->generateValidCnpj(5000 + $i) : null,
                'tipo' => $type->value,
                'endereco' => fake()->streetAddress().', '.fake()->city().' — MG',
                'observacoes' => 'Cadastro demo bulk',
                'ativo' => $i % 17 !== 0,
            ]);

            $companyService->syncContactsAndEmails(
                $company,
                [[
                    'nome' => fake()->name(),
                    'cargo' => $cargos[$i % count($cargos)],
                    'telefone' => fake()->phoneNumber(),
                    'principal' => true,
                ]],
                [[
                    'email' => 'contato'.($i + 1).'@empresa-demo.local',
                    'rotulo' => 'Comercial',
                    'principal' => true,
                ]],
            );

            $companies[] = $company;
        }

        $existingPeople = Person::count();

        for ($i = $existingPeople; $i < $this->peopleTarget; $i++) {
            $company = $companies[$i % max(1, count($companies))] ?? null;

            Person::create([
                'nome' => fake()->name(),
                'cpf' => $this->generateValidCpf(2000 + $i),
                'data_nascimento' => now()->subYears(rand(25, 58))->subDays(rand(1, 300))->toDateString(),
                'telefone' => fake()->phoneNumber(),
                'telefone_secundario' => $i % 3 === 0 ? fake()->phoneNumber() : null,
                'email' => 'pessoa'.($i + 1).'@demo.local',
                'cargo' => $cargos[$i % count($cargos)],
                'company_id' => $i % 8 !== 0 ? $company?->id : null,
                'endereco_residencial' => fake()->streetAddress().', '.fake()->city(),
                'endereco_comercial' => $company ? $company->endereco : null,
                'observacoes' => 'Pessoa demo bulk',
                'ativo' => $i % 19 !== 0,
                'created_by' => $admin->id,
            ]);
        }
    }

    private function seedPricingForCompany(): void
    {
        $categoryPrices = [
            'Escavadeira' => ['diaria' => 850, 'semanal' => 4500, 'mensal' => 14000],
            'Mini Escavadeira' => ['diaria' => 420, 'semanal' => 2200, 'mensal' => 6800],
            'Betoneira' => ['diaria' => 85, 'semanal' => 450, 'mensal' => 1400],
            'Gerador' => ['diaria' => 120, 'semanal' => 650, 'mensal' => 2000],
        ];

        $modelOverrides = [
            ['marca' => 'CAT', 'modelo' => '320D', 'diaria' => 950],
            ['marca' => 'Honda', 'modelo' => 'EG 6500', 'diaria' => 150],
            ['marca' => 'Yanmar', 'modelo' => 'Vio45', 'diaria' => 480],
        ];

        foreach (EquipmentCategory::query()->where('ativo', true)->get() as $category) {
            $prices = $categoryPrices[$category->nome] ?? ['diaria' => 100, 'semanal' => 500, 'mensal' => 1500];

            foreach ($prices as $periodo => $valor) {
                EquipmentPricing::firstOrCreate(
                    [
                        'equipment_category_id' => $category->id,
                        'periodo' => $periodo,
                        'equipment_model_id' => null,
                    ],
                    ['valor' => $valor, 'ativo' => true],
                );
            }
        }

        foreach ($modelOverrides as $override) {
            $model = EquipmentModel::query()
                ->where('marca', $override['marca'])
                ->where('modelo', $override['modelo'])
                ->first();

            if (! $model) {
                continue;
            }

            EquipmentPricing::updateOrCreate(
                [
                    'equipment_model_id' => $model->id,
                    'periodo' => RentalPricingPeriod::Diaria->value,
                    'equipment_category_id' => null,
                ],
                ['valor' => $override['diaria'], 'ativo' => true],
            );
        }
    }

    private function seedPreventiveRules(User $admin): void
    {
        $intervals = [200, 250, 300, 400, 500];

        foreach (EquipmentModel::query()->where('ativo', true)->get() as $model) {
            PreventiveMaintenanceRule::firstOrCreate(
                ['equipment_model_id' => $model->id],
                [
                    'interval_horas' => $intervals[$model->id % count($intervals)],
                    'descricao' => "Revisão preventiva — {$model->marca} {$model->modelo}",
                    'ativo' => true,
                    'created_by' => $admin->id,
                ],
            );
        }
    }

    /**
     * @param  list<Customer>  $customers
     * @param  list<Asset>  $assets
     */
    private function seedRentalsForCompany(User $admin, array $customers, array $assets): void
    {
        $rentalService = app(RentalService::class);
        $statusService = app(AssetStatusService::class);
        $saida = array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true);
        $retorno = array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true);

        $available = collect($assets)
            ->map(fn (Asset $a) => $a->fresh())
            ->filter(fn (Asset $a) => $a->status === AssetStatus::Disponivel->value)
            ->values();

        $customerCount = max(1, count($customers));
        $valorBase = [180, 250, 320, 450, 580, 720, 890, 1100, 1350, 1600, 2100, 2800];
        $periods = [RentalPricingPeriod::Diaria, RentalPricingPeriod::Semanal, RentalPricingPeriod::Mensal];

        // Locado — equipamento em campo
        for ($i = 0; $i < $this->rentalCount('locado') && $available->isNotEmpty(); $i++) {
            $rental = $rentalService->reserve(
                $available->shift(),
                $customers[$i % $customerCount],
                now()->addDays(rand(2, 30)),
                'Locação demo em campo.',
                $admin,
                fake()->streetAddress().', Obra '.($i + 1),
                $periods[$i % count($periods)],
            );
            $rental = $rentalService->checkout($rental, $saida, null, $admin);
            $rental->update([
                'valor_faturamento' => $valorBase[$i % count($valorBase)],
                'expected_return_at' => now()->addDays(rand(1, 45))->toDateString(),
                'checkout_at' => now()->subDays(rand(1, 20)),
            ]);
        }

        // Reservado — aguardando saída
        for ($i = 0; $i < $this->rentalCount('reservado') && $available->isNotEmpty(); $i++) {
            $rentalService->reserve(
                $available->shift(),
                $customers[($i + 7) % $customerCount],
                now()->addDays(rand(3, 20)),
                'Reserva aguardando saída.',
                $admin,
                fake()->streetAddress(),
                $periods[($i + 1) % count($periods)],
            );
        }

        // Em inspeção — retorno registrado
        for ($i = 0; $i < $this->rentalCount('inspecao') && $available->isNotEmpty(); $i++) {
            $rental = $rentalService->reserve(
                $available->shift(),
                $customers[($i + 15) % $customerCount],
                now()->addDays(7),
                null,
                $admin,
            );
            $rental = $rentalService->checkout($rental, $saida, null, $admin);
            $rental->update(['checkout_at' => now()->subDays(rand(8, 25))]);
            $rentalService->registerReturn($rental, $retorno, 'Retorno para inspeção.', $admin);
        }

        // Cancelado — reserva desistida
        for ($i = 0; $i < $this->rentalCount('cancelado') && $available->isNotEmpty(); $i++) {
            $rental = $rentalService->reserve(
                $available->shift(),
                $customers[($i + 22) % $customerCount],
                now()->addDays(10),
                'Reserva que será cancelada.',
                $admin,
            );
            $rentalService->cancel($rental, 'Cliente desistiu da reserva (demo).', $admin);
        }

        // Locado com manutenção em campo
        for ($i = 0; $i < $this->rentalCount('manut_campo') && $available->isNotEmpty(); $i++) {
            $rental = $rentalService->reserve(
                $available->shift(),
                $customers[($i + 30) % $customerCount],
                now()->addDays(10),
                'Locação com manutenção em campo.',
                $admin,
                fake()->streetAddress(),
            );
            $rental = $rentalService->checkout($rental, $saida, null, $admin);
            $statusService->transition(
                $rental->asset->fresh(),
                AssetStatus::EmManutencaoCampo,
                'Manutenção em campo durante locação (demo)',
                $admin,
            );
        }

        // Concluído — fluxo completo via serviço (datas históricas aplicadas após reserve)
        for ($i = 0; $i < $this->rentalCount('concluido_svc') && $available->isNotEmpty(); $i++) {
            $completedAt = now()->subDays(1 + ($i % 89));
            $returnAt = $completedAt->copy()->subHours(rand(2, 48));
            $checkoutAt = $returnAt->copy()->subDays(rand(3, 15));
            $reservedAt = $checkoutAt->copy()->subDays(rand(1, 5));

            $rental = $rentalService->reserve(
                $available->shift(),
                $customers[($i + 40) % $customerCount],
                now()->addDays(rand(10, 30)),
                'Locação histórica concluída.',
                $admin,
            );

            $rental = $rentalService->checkout($rental, $saida, null, $admin);
            $rental->update([
                'reserved_at' => $reservedAt,
                'checkout_at' => $checkoutAt,
                'expected_return_at' => $returnAt->copy()->startOfDay(),
            ]);
            $rental = $rentalService->registerReturn($rental, $retorno, null, $admin);
            $rental->update(['returned_at' => $returnAt]);
            $rental = $rentalService->completeInspection($rental, $i % 7 === 0, $i % 7 === 0 ? 'Avaria leve na inspeção.' : null, $admin);
            $rental->update([
                'valor_faturamento' => $valorBase[($i + 2) % count($valorBase)],
                'completed_at' => $completedAt,
            ]);
        }

        // Histórico concluído adicional (insert direto — volume sem travar patrimônio)
        $companyId = ActiveOperatingCompany::id();
        $slugPrefix = strtoupper(substr(OperatingCompany::find($companyId)?->slug ?? 'x', 0, 2));

        for ($i = 0; $i < $this->rentalCount('concluido_hist'); $i++) {
            $asset = $available->isNotEmpty() ? $available->shift() : $assets[array_rand($assets)];
            $completedAt = now()->subDays(rand(5, 400));

            Rental::withoutGlobalScope('operating_company')->create([
                'operating_company_id' => $companyId,
                'codigo' => sprintf('LOC-%s-H%04d', $slugPrefix, $i + ($companyId * 1000)),
                'asset_id' => $asset->id,
                'customer_id' => $customers[($i + 3) % $customerCount]->id,
                'status' => RentalStatus::Concluido->value,
                'reserved_at' => $completedAt->copy()->subDays(20),
                'checkout_at' => $completedAt->copy()->subDays(18),
                'returned_at' => $completedAt->copy()->subDay(),
                'completed_at' => $completedAt,
                'expected_return_at' => $completedAt->copy()->subDays(2)->toDateString(),
                'valor_faturamento' => $valorBase[$i % count($valorBase)],
                'commercial_user_id' => $admin->id,
                'reserved_by' => $admin->id,
                'checkout_by' => $admin->id,
                'returned_by' => $admin->id,
                'completed_by' => $admin->id,
                'local_obra' => fake()->streetAddress(),
            ]);
        }
    }

    /**
     * Patrimônios ainda disponíveis recebem status diversos (bloqueado, sucata, extraviado).
     *
     * @param  list<Asset>  $assets
     */
    private function applyFleetStatusMix(User $admin, array $assets): void
    {
        $statusService = app(AssetStatusService::class);

        $pool = collect($assets)
            ->map(fn (Asset $a) => $a->fresh())
            ->filter(fn (Asset $a) => $a->status === AssetStatus::Disponivel->value)
            ->shuffle()
            ->values();

        $mix = match ($this->rentalProfile) {
            'compact' => [
                [AssetStatus::Bloqueado, 2, 'Bloqueado para auditoria interna (demo)'],
                [AssetStatus::Extraviado, 1, 'Extraviado — aguardando localização (demo)'],
                [AssetStatus::Sucata, 1, 'Baixado como sucata (demo)'],
            ],
            'standard' => [
                [AssetStatus::Bloqueado, 5, 'Bloqueado para auditoria interna (demo)'],
                [AssetStatus::Extraviado, 3, 'Extraviado — aguardando localização (demo)'],
                [AssetStatus::Sucata, 2, 'Baixado como sucata (demo)'],
            ],
            default => [
                [AssetStatus::Bloqueado, 6, 'Bloqueado para auditoria interna (demo)'],
                [AssetStatus::Extraviado, 3, 'Extraviado — aguardando localização (demo)'],
                [AssetStatus::Sucata, 2, 'Baixado como sucata (demo)'],
            ],
        };

        $index = 0;

        foreach ($mix as [$status, $count, $motivo]) {
            for ($i = 0; $i < $count && $index < $pool->count(); $i++, $index++) {
                try {
                    $statusService->transition($pool[$index], $status, $motivo, $admin);
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
        }
    }

    private function seedCompletedMaintenanceHistory(User $admin): void
    {
        $maintenanceService = app(MaintenanceOrderService::class);
        $created = 0;

        $candidates = Asset::withoutGlobalScope('operating_company')
            ->where('status', AssetStatus::Disponivel->value)
            ->inRandomOrder()
            ->limit($this->completedOrdersTarget * 2)
            ->get();

        foreach ($candidates as $asset) {
            if ($created >= $this->completedOrdersTarget) {
                break;
            }

            ActiveOperatingCompany::set((int) $asset->operating_company_id);

            if (MaintenanceOrder::query()->where('asset_id', $asset->id)->open()->exists()) {
                continue;
            }

            $parts = PartCatalogItem::query()->where('ativo', true)->get();
            if ($parts->isEmpty()) {
                break;
            }

            try {
                $order = $maintenanceService->open(
                    $asset,
                    'Revisão pós-locação (demo histórico)',
                    MaintenanceOrderType::Corretiva,
                    user: $admin,
                );
                $maintenanceService->start($order, $admin);

                $part = $parts[$created % $parts->count()];
                $maintenanceService->addPart(
                    $order,
                    $part->descricao,
                    (float) rand(1, 3),
                    $part->codigo_peca,
                    (float) $part->valor_unitario_padrao,
                );
                $maintenanceService->addLaborHour($order, 'Serviço mecânico', (float) rand(1, 6), null, $admin);
                $maintenanceService->complete($order->fresh(), 'Concluída para demonstração.', $admin);
                $order->fresh()->update([
                    'completed_at' => now()->subDays(rand(3, 180)),
                ]);
                $created++;
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
    }

    /**
     * 20 OS abertas no funil: aberta → em execução → aguardando peça.
     *
     * @param  list<Asset>  $allAssets
     */
    private function seedOpenMaintenanceFunnel(User $admin, array $allAssets): void
    {
        $maintenanceService = app(MaintenanceOrderService::class);
        $total = $this->openOrdersTotal;
        $distribution = [
            MaintenanceOrderStatus::Aberta->value => (int) max(1, round($total * 0.4)),
            MaintenanceOrderStatus::EmExecucao->value => (int) max(1, round($total * 0.35)),
            MaintenanceOrderStatus::AguardandoPeca->value => (int) max(1, $total - (int) max(1, round($total * 0.4)) - (int) max(1, round($total * 0.35))),
        ];

        $pool = collect($allAssets)
            ->map(fn (Asset $a) => $a->fresh())
            ->filter(fn (Asset $a) => $a->status === AssetStatus::Disponivel->value)
            ->shuffle()
            ->take($this->openOrdersTotal)
            ->values();

        if ($pool->count() < $this->openOrdersTotal) {
            $this->command?->warn(sprintf(
                'Apenas %d patrimônios disponíveis para OS (meta: %d).',
                $pool->count(),
                $this->openOrdersTotal,
            ));
        }

        $problems = [
            'Vazamento no sistema hidráulico',
            'Motor com ruído anormal',
            'Partida difícil a frio',
            'Correia desgastada',
            'Falha no painel elétrico',
            'Baixa pressão de óleo',
            'Mangueira rompida',
            'Rolamento com folga',
            'Superaquecimento em operação',
            'Vibração excessiva',
        ];

        $assetIndex = 0;
        $problemIndex = 0;

        foreach ($distribution as $targetStatus => $count) {
            for ($n = 0; $n < $count && $assetIndex < $pool->count(); $n++) {
                $asset = $pool[$assetIndex++];
                ActiveOperatingCompany::set((int) $asset->operating_company_id);

                $order = $maintenanceService->open(
                    $asset,
                    $problems[$problemIndex % count($problems)],
                    MaintenanceOrderType::Corretiva,
                    user: $admin,
                );
                $problemIndex++;

                if ($targetStatus === MaintenanceOrderStatus::EmExecucao->value) {
                    $maintenanceService->start($order, $admin);
                } elseif ($targetStatus === MaintenanceOrderStatus::AguardandoPeca->value) {
                    $maintenanceService->start($order, $admin);
                    $maintenanceService->waitForPart($order, 'Peça em cotação com fornecedor.', $admin);
                }
            }
        }
    }

    protected function rentalCount(string $key): int
    {
        $profiles = [
            'compact' => [
                'locado' => 6,
                'reservado' => 4,
                'inspecao' => 2,
                'cancelado' => 2,
                'manut_campo' => 2,
                'concluido_svc' => 8,
                'concluido_hist' => 12,
            ],
            'standard' => [
                'locado' => 22,
                'reservado' => 14,
                'inspecao' => 8,
                'cancelado' => 6,
                'manut_campo' => 5,
                'concluido_svc' => 28,
                'concluido_hist' => 40,
            ],
            'bulk' => [
                'locado' => 40,
                'reservado' => 15,
                'inspecao' => 10,
                'cancelado' => 8,
                'manut_campo' => 5,
                'concluido_svc' => 30,
                'concluido_hist' => 50,
            ],
        ];

        $profile = $profiles[$this->rentalProfile] ?? $profiles['bulk'];

        return $profile[$key] ?? 0;
    }

    /**
     * @param  list<Customer>  $customers
     * @param  list<Asset>  $allAssets
     */
    protected function seedSupplementalModules(User $admin, array $customers, array $allAssets): void
    {
        $this->seedYardsForAllCompanies();
        $this->seedPartSuppliers();
        $this->assignAssetsToYards($allAssets);

        foreach (OperatingCompany::query()->where('ativo', true)->orderBy('id')->get() as $company) {
            ActiveOperatingCompany::set($company->id);
            $this->seedDriversForCompany($company);
            $companyAssets = collect($allAssets)
                ->filter(fn (Asset $a) => (int) $a->operating_company_id === (int) $company->id)
                ->values();
            $this->seedRentalQuotesForCompany($admin, $customers, $companyAssets);
            $this->seedPartPurchaseOrdersForCompany($admin);
            $this->seedPreventiveOrdersForCompany($admin, $companyAssets);
        }
    }

    private function seedYardsForAllCompanies(): void
    {
        $yardSets = [
            'acesso' => [
                ['nome' => 'Pátio BH Principal', 'cidade' => 'Belo Horizonte', 'principal' => true],
                ['nome' => 'Filial Contagem', 'cidade' => 'Contagem', 'principal' => false],
            ],
            'supermaquinas' => [
                ['nome' => 'Pátio Super — Betim', 'cidade' => 'Betim', 'principal' => true],
                ['nome' => 'Base Super — Sarzedo', 'cidade' => 'Sarzedo', 'principal' => false],
            ],
        ];

        foreach (OperatingCompany::query()->where('ativo', true)->get() as $company) {
            ActiveOperatingCompany::set($company->id);

            foreach ($yardSets[$company->slug] ?? [['nome' => "Pátio {$company->nome}", 'cidade' => 'Belo Horizonte', 'principal' => true]] as $yard) {
                Yard::updateOrCreate(
                    ['operating_company_id' => $company->id, 'nome' => $yard['nome']],
                    [
                        'cidade' => $yard['cidade'],
                        'endereco' => "Rua Demo, 100 — {$yard['cidade']}",
                        'telefone' => '(31) 3333-'.rand(1000, 9999),
                        'principal' => $yard['principal'],
                        'ativo' => true,
                    ],
                );
            }
        }
    }

    private function seedPartSuppliers(): void
    {
        $baseNames = [
            'Auto Peças Minas', 'Hidráulica Industrial MG', 'Distribuidora CAT Parts',
            'Ferragens e Rolamentos BH', 'Motores & Cia', 'Filtros Brasil',
            'Mangueiras e Conexões', 'Lubrificantes Centro-Oeste',
        ];

        for ($i = 0; $i < $this->suppliersTarget; $i++) {
            $name = $baseNames[$i % count($baseNames)].($i >= count($baseNames) ? ' '.($i + 1) : '');
            Company::updateOrCreate(
                ['nome' => $name],
                [
                    'cnpj' => $this->generateValidCnpj(9000 + $i),
                    'tipo' => CompanyType::Fornecedor->value,
                    'endereco' => fake()->streetAddress().', Contagem — MG',
                    'observacoes' => 'Fornecedor demo',
                    'ativo' => true,
                ],
            );
        }
    }

    /** @param  list<Asset>  $allAssets */
    private function assignAssetsToYards(array $allAssets): void
    {
        foreach ($allAssets as $asset) {
            ActiveOperatingCompany::set((int) $asset->operating_company_id);
            $yard = Yard::query()->where('ativo', true)->inRandomOrder()->first();

            if ($yard && $asset->yard_id === null) {
                $asset->update(['yard_id' => $yard->id, 'localizacao' => $yard->nome]);
            }
        }
    }

    private function seedDriversForCompany(OperatingCompany $company): void
    {
        if ($this->driversPerCompany < 1) {
            return;
        }

        $driverNames = ['Carlos Motorista', 'Paulo Entregas', 'Ricardo Logística'];

        for ($i = 0; $i < $this->driversPerCompany; $i++) {
            DeliveryDriver::updateOrCreate(
                [
                    'operating_company_id' => $company->id,
                    'nome' => $driverNames[$i % count($driverNames)].' ('.Str::title($company->slug).')',
                ],
                [
                    'cnh' => 'MG'.str_pad((string) (10000000000 + $company->id * 10 + $i), 11, '0', STR_PAD_LEFT),
                    'telefone' => '(31) 9'.rand(1000, 9999).'-'.rand(1000, 9999),
                    'ativo' => true,
                ],
            );
        }
    }

    /** @param  list<Customer>  $customers */
    private function seedRentalQuotesForCompany(User $admin, array $customers, $companyAssets): void
    {
        if ($companyAssets->isEmpty() || $customers === []) {
            return;
        }

        $quoteService = app(RentalQuoteService::class);
        $available = $companyAssets
            ->map(fn (Asset $a) => $a->fresh())
            ->filter(fn (Asset $a) => $a->status === AssetStatus::Disponivel->value)
            ->values();

        $customerCount = max(1, count($customers));

        for ($i = 0; $i < $this->quotesRascunhoPerCompany && $available->isNotEmpty(); $i++) {
            $quoteService->create(
                $available->shift(),
                $customers[$i % $customerCount],
                now()->addDays(rand(5, 20)),
                fake()->streetAddress().', Obra orçamento '.($i + 1),
                'Orçamento demo',
                RentalPricingPeriod::Diaria,
                $admin,
            );
        }

        for ($i = 0; $i < $this->quotesEnviadoPerCompany && $available->isNotEmpty(); $i++) {
            $quote = $quoteService->create(
                $available->shift(),
                $customers[($i + 2) % $customerCount],
                now()->addDays(rand(7, 25)),
                fake()->streetAddress(),
                'Orçamento enviado ao cliente',
                RentalPricingPeriod::Semanal,
                $admin,
            );
            $quoteService->send($quote, 10, $admin);
        }

        for ($i = 0; $i < $this->quotesExpiradoPerCompany && $available->isNotEmpty(); $i++) {
            $expired = $quoteService->create(
                $available->shift(),
                $customers[($i + 5) % $customerCount],
                now()->addDays(14),
                fake()->streetAddress(),
                'Orçamento expirado (demo)',
                RentalPricingPeriod::Mensal,
                $admin,
            );
            $quoteService->send($expired, 1, $admin);
            $expired->update([
                'valid_until' => now()->subDay(),
                'status' => RentalQuoteStatus::Expirado->value,
            ]);
        }
    }

    private function seedPartPurchaseOrdersForCompany(User $admin): void
    {
        $purchaseService = app(PartPurchaseOrderService::class);
        $supplier = Company::query()->where('tipo', CompanyType::Fornecedor->value)->where('ativo', true)->inRandomOrder()->first();
        $parts = PartCatalogItem::query()->where('ativo', true)->limit(4)->get();

        if (! $supplier || $parts->isEmpty()) {
            return;
        }

        $items = $parts->map(fn (PartCatalogItem $part) => [
            'part_catalog_item_id' => $part->id,
            'quantidade' => (float) rand(2, 12),
        ])->all();

        for ($n = 0; $n < $this->purchaseOrdersPerCompany; $n++) {
            $order = $purchaseService->create(
                $supplier,
                $items,
                "Pedido demo #{$n} — ".(['rascunho', 'aguardando envio', 'em trânsito', 'recebimento'][$n % 4]),
                $admin,
            );

            if ($n % 3 === 1) {
                $purchaseService->markSent($order, $admin);
            }

            if ($n % 3 === 2) {
                $purchaseService->markSent($order, $admin);
                $purchaseService->receive($order, $admin);
            }
        }
    }

    private function seedPreventiveOrdersForCompany(User $admin, $companyAssets): void
    {
        $maintenanceService = app(MaintenanceOrderService::class);
        $created = 0;

        foreach ($companyAssets as $asset) {
            if ($created >= $this->preventiveOrdersPerCompany) {
                break;
            }

            $asset = $asset->fresh();

            if ($asset->status !== AssetStatus::Disponivel->value) {
                continue;
            }

            if (MaintenanceOrder::query()->where('asset_id', $asset->id)->open()->exists()) {
                continue;
            }

            $rule = PreventiveMaintenanceRule::query()
                ->where('equipment_model_id', $asset->equipment_model_id)
                ->where('ativo', true)
                ->first();

            if (! $rule) {
                continue;
            }

            try {
                $order = $maintenanceService->openPreventive($asset, $rule, $admin);

                if ($created === 1) {
                    $maintenanceService->start($order, $admin);
                }

                $created++;
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
    }

    private function seedPartCatalog(): void
    {
        $descriptions = [
            'Escova de carvão universal', 'Mandril SDS Plus', 'Correia V A-42', 'Filtro de ar gerador',
            'Óleo lubrificante 1L', 'Rolamento 6203', 'Vela de ignição', 'Kit gaxetas betoneira',
            'Mangueira hidráulica 1/2"', 'Filtro de óleo motor', 'Pastilha de freio', 'Disco de corte',
            'Lona de transmissão', 'Bomba d\'água', 'Radiador compacto', 'Mangueira combustível',
            'Filtro diesel', 'Bateria 12V 60Ah', 'Alternador 12V', 'Motor de partida',
            'Junta do cabeçote', 'Kit embreagem', 'Cilindro hidráulico', 'Válvula de alívio',
        ];

        foreach (OperatingCompany::query()->where('ativo', true)->get() as $company) {
            ActiveOperatingCompany::set($company->id);

            for ($i = 0; $i < $this->partCatalogItemCount; $i++) {
                $codigo = sprintf('PEC-%03d', $i + 1);
                PartCatalogItem::updateOrCreate(
                    ['codigo_peca' => $codigo.'-'.$company->slug],
                    [
                        'descricao' => $descriptions[$i % count($descriptions)].' #'.($i + 1),
                        'valor_unitario_padrao' => round(15 + ($i * 7.5) + rand(0, 50), 2),
                        'estoque_atual' => $this->seedSupplemental ? rand(3, 40) : 0,
                        'estoque_minimo' => $this->seedSupplemental ? rand(5, 15) : 0,
                        'ativo' => true,
                    ],
                );
            }
        }
    }

    private function generateValidCpf(int $seed): string
    {
        $base = str_pad((string) (100000000 + ($seed % 899999999)), 9, '0', STR_PAD_LEFT);
        $digits = $base.$this->cpfCheckDigit($base, 10).$this->cpfCheckDigit($base.$this->cpfCheckDigit($base, 10), 11);

        return preg_match('/^(\d)\1{10}$/', $digits) ? $this->generateValidCpf($seed + 17) : $digits;
    }

    private function cpfCheckDigit(string $base, int $weightStart): string
    {
        $sum = 0;
        for ($i = 0; $i < strlen($base); $i++) {
            $sum += (int) $base[$i] * ($weightStart - $i);
        }
        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }

    private function generateValidCnpj(int $seed): string
    {
        $base = str_pad((string) (10000000 + ($seed % 89999999)), 8, '0', STR_PAD_LEFT).'0001';
        $first = $this->cnpjCheckDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $second = $this->cnpjCheckDigit($base.$first, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $base.$first.$second;
    }

    /** @param list<int> $weights */
    private function cnpjCheckDigit(string $base, array $weights): string
    {
        $sum = 0;
        for ($i = 0; $i < count($weights); $i++) {
            $sum += (int) $base[$i] * $weights[$i];
        }
        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }
}
