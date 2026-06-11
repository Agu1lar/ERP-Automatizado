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
use App\Models\Domain\Rental\Rental;
use App\Models\User;
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
    private const CUSTOMERS_TARGET = 250;

    private const COMPANIES_TARGET = 45;

    private const PEOPLE_TARGET = 120;

    private const ASSETS_PER_COMPANY = 150;

    private const OPEN_ORDERS_TOTAL = 20;

    private const COMPLETED_ORDERS_TARGET = 40;

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
            'Bulk demo: %d empresas | %d pessoas | %d clientes | %d preços | %d patrimônios | %d locações | %d OS abertas',
            Company::count(),
            Person::count(),
            Customer::count(),
            EquipmentPricing::withoutGlobalScope('operating_company')->count(),
            $assets->count(),
            $rentals->count(),
            $openOrders,
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

        for ($i = $existing; $i < self::CUSTOMERS_TARGET; $i++) {
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
        $unitsPerModel = (int) ceil(self::ASSETS_PER_COMPANY / (
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

        for ($i = count($companies); $i < self::COMPANIES_TARGET; $i++) {
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

        for ($i = $existingPeople; $i < self::PEOPLE_TARGET; $i++) {
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
        for ($i = 0; $i < 40 && $available->isNotEmpty(); $i++) {
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
        for ($i = 0; $i < 15 && $available->isNotEmpty(); $i++) {
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
        for ($i = 0; $i < 10 && $available->isNotEmpty(); $i++) {
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
        for ($i = 0; $i < 8 && $available->isNotEmpty(); $i++) {
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
        for ($i = 0; $i < 5 && $available->isNotEmpty(); $i++) {
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

        // Concluído — fluxo completo via serviço
        for ($i = 0; $i < 30 && $available->isNotEmpty(); $i++) {
            $rental = $rentalService->reserve(
                $available->shift(),
                $customers[($i + 40) % $customerCount],
                now()->subDays(rand(30, 90)),
                'Locação histórica concluída.',
                $admin,
            );

            $completedAt = now()->subDays(1 + ($i % 89));
            $returnAt = $completedAt->copy()->subHours(rand(2, 48));
            $checkoutAt = $returnAt->copy()->subDays(rand(3, 15));

            $rental = $rentalService->checkout($rental, $saida, null, $admin);
            $rental->update(['checkout_at' => $checkoutAt]);
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

        for ($i = 0; $i < 50; $i++) {
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

        $mix = [
            [AssetStatus::Bloqueado, 6, 'Bloqueado para auditoria interna (demo)'],
            [AssetStatus::Extraviado, 3, 'Extraviado — aguardando localização (demo)'],
            [AssetStatus::Sucata, 2, 'Baixado como sucata (demo)'],
        ];

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
            ->limit(self::COMPLETED_ORDERS_TARGET * 2)
            ->get();

        foreach ($candidates as $asset) {
            if ($created >= self::COMPLETED_ORDERS_TARGET) {
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
        $distribution = [
            MaintenanceOrderStatus::Aberta->value => 7,
            MaintenanceOrderStatus::EmExecucao->value => 7,
            MaintenanceOrderStatus::AguardandoPeca->value => 6,
        ];

        $pool = collect($allAssets)
            ->map(fn (Asset $a) => $a->fresh())
            ->filter(fn (Asset $a) => $a->status === AssetStatus::Disponivel->value)
            ->shuffle()
            ->take(self::OPEN_ORDERS_TOTAL)
            ->values();

        if ($pool->count() < self::OPEN_ORDERS_TOTAL) {
            $this->command?->warn(sprintf(
                'Apenas %d patrimônios disponíveis para OS (meta: %d).',
                $pool->count(),
                self::OPEN_ORDERS_TOTAL,
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

    private function seedPartCatalog(): void
    {
        $parts = [
            ['codigo' => 'PEC-001', 'descricao' => 'Escova de carvão universal', 'valor' => 45.90],
            ['codigo' => 'PEC-002', 'descricao' => 'Mandril SDS Plus', 'valor' => 89.00],
            ['codigo' => 'PEC-003', 'descricao' => 'Correia V A-42', 'valor' => 32.50],
            ['codigo' => 'PEC-004', 'descricao' => 'Filtro de ar gerador', 'valor' => 58.00],
            ['codigo' => 'PEC-005', 'descricao' => 'Óleo lubrificante 1L', 'valor' => 28.90],
            ['codigo' => 'PEC-006', 'descricao' => 'Rolamento 6203', 'valor' => 22.00],
            ['codigo' => 'PEC-007', 'descricao' => 'Vela de ignição', 'valor' => 18.50],
            ['codigo' => 'PEC-008', 'descricao' => 'Kit gaxetas betoneira', 'valor' => 120.00],
            ['codigo' => 'PEC-009', 'descricao' => 'Mangueira hidráulica 1/2"', 'valor' => 185.00],
            ['codigo' => 'PEC-010', 'descricao' => 'Filtro de óleo motor', 'valor' => 42.00],
        ];

        foreach (OperatingCompany::query()->where('ativo', true)->get() as $company) {
            ActiveOperatingCompany::set($company->id);

            foreach ($parts as $part) {
                PartCatalogItem::firstOrCreate(
                    ['codigo_peca' => $part['codigo'].'-'.$company->slug],
                    [
                        'descricao' => $part['descricao'],
                        'valor_unitario_padrao' => $part['valor'],
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
