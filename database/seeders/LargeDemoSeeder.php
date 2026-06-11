<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Enums\RentalPricingPeriod;
use App\Enums\ReceivableTitleStatus;
use App\Services\ReceivableTitleService;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\MaintenanceOrderService;
use App\Services\RentalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LargeDemoSeeder extends Seeder
{
  /** @var list<array{marca: string, modelo: string, specs: array<string, string>}> */
  private const MODELS_BY_CATEGORY = [
    'Betoneira' => [
      ['marca' => 'CSM', 'modelo' => 'CSM 400', 'specs' => ['capacidade' => '400L', 'motor' => '2CV']],
      ['marca' => 'Menegotti', 'modelo' => 'MAX 400', 'specs' => ['capacidade' => '400L']],
      ['marca' => 'Tramontina', 'modelo' => 'PRO 120', 'specs' => ['capacidade' => '120L']],
    ],
    'Martelete' => [
      ['marca' => 'Bosch', 'modelo' => 'GBH 2-24', 'specs' => ['potencia' => '790W']],
      ['marca' => 'Makita', 'modelo' => 'HR2630', 'specs' => ['potencia' => '800W']],
      ['marca' => 'DeWalt', 'modelo' => 'D25133K', 'specs' => ['potencia' => '800W']],
    ],
    'Andaime' => [
      ['marca' => 'Metálica', 'modelo' => 'Módulo 1x1', 'specs' => ['altura' => '1m']],
      ['marca' => 'Metálica', 'modelo' => 'Torre 2m', 'specs' => ['altura' => '2m']],
      ['marca' => 'Metálica', 'modelo' => 'Plataforma', 'specs' => ['largura' => '0,73m']],
    ],
    'Gerador' => [
      ['marca' => 'Honda', 'modelo' => 'EG 6500', 'specs' => ['potencia' => '5,5 kVA']],
      ['marca' => 'Toyama', 'modelo' => 'TG2800CXR', 'specs' => ['potencia' => '2,8 kVA']],
      ['marca' => 'Bambozzi', 'modelo' => 'BBS 11000', 'specs' => ['potencia' => '11 kVA']],
    ],
    'Outros' => [
      ['marca' => 'Schulz', 'modelo' => 'CPA 10', 'specs' => ['tipo' => 'Compressor']],
      ['marca' => 'Karcher', 'modelo' => 'HD 585', 'specs' => ['tipo' => 'Lavadora']],
      ['marca' => 'Makita', 'modelo' => 'UC4041A', 'specs' => ['tipo' => 'Motosserra']],
    ],
  ];

  /** @var list<array{nome: string, tipo: string, cidade: string}> */
  private const CUSTOMER_NAMES = [
    ['nome' => 'Construtora Horizonte Ltda', 'tipo' => 'cnpj', 'cidade' => 'São Paulo'],
    ['nome' => 'Obras Rápidas ME', 'tipo' => 'cnpj', 'cidade' => 'Guarulhos'],
    ['nome' => 'Reformas Silva', 'tipo' => 'cpf', 'cidade' => 'Osasco'],
    ['nome' => 'Engenharia Delta S.A.', 'tipo' => 'cnpj', 'cidade' => 'Campinas'],
    ['nome' => 'Pedro Almeida Construções', 'tipo' => 'cpf', 'cidade' => 'Santo André'],
    ['nome' => 'Mega Obra Empreendimentos', 'tipo' => 'cnpj', 'cidade' => 'São Bernardo'],
    ['nome' => 'Fundações Forte', 'tipo' => 'cnpj', 'cidade' => 'Diadema'],
    ['nome' => 'Carlos Mendes Reformas', 'tipo' => 'cpf', 'cidade' => 'Mauá'],
    ['nome' => 'Constrular Materiais', 'tipo' => 'cnpj', 'cidade' => 'Sorocaba'],
    ['nome' => 'Ana Paula Arquitetura', 'tipo' => 'cpf', 'cidade' => 'Santos'],
    ['nome' => 'Terraplanagem Sul', 'tipo' => 'cnpj', 'cidade' => 'Jundiaí'],
    ['nome' => 'João Batista Obras', 'tipo' => 'cpf', 'cidade' => 'Barueri'],
    ['nome' => 'Edificar Projetos', 'tipo' => 'cnpj', 'cidade' => 'Ribeirão Preto'],
    ['nome' => 'Marcos & Filhos', 'tipo' => 'cpf', 'cidade' => 'Piracicaba'],
    ['nome' => 'Urbano Construções', 'tipo' => 'cnpj', 'cidade' => 'São José dos Campos'],
    ['nome' => 'Luciana Costa Engenharia', 'tipo' => 'cpf', 'cidade' => 'Taubaté'],
    ['nome' => 'Base Forte Fundações', 'tipo' => 'cnpj', 'cidade' => 'Limeira'],
    ['nome' => 'Ricardo Pinturas', 'tipo' => 'cpf', 'cidade' => 'Americana'],
    ['nome' => 'Grupo Estrutural', 'tipo' => 'cnpj', 'cidade' => 'Araraquara'],
    ['nome' => 'Fernanda Lima Reformas', 'tipo' => 'cpf', 'cidade' => 'Bauru'],
    ['nome' => 'Cimento & Aço Ltda', 'tipo' => 'cnpj', 'cidade' => 'Franca'],
    ['nome' => 'Paulo Henrique Obras', 'tipo' => 'cpf', 'cidade' => 'Marília'],
    ['nome' => 'Topografia Brasil', 'tipo' => 'cnpj', 'cidade' => 'Presidente Prudente'],
    ['nome' => 'Eliane Souza Construções', 'tipo' => 'cpf', 'cidade' => 'São Carlos'],
    ['nome' => 'Montagem Industrial RS', 'tipo' => 'cnpj', 'cidade' => 'Indaiatuba'],
    ['nome' => 'Bruno Carvalho ME', 'tipo' => 'cpf', 'cidade' => 'Itu'],
    ['nome' => 'Projetos Verticais', 'tipo' => 'cnpj', 'cidade' => 'Atibaia'],
    ['nome' => 'Gabriel Torres Fundações', 'tipo' => 'cpf', 'cidade' => 'Bragança Paulista'],
    ['nome' => 'Alfa Engenharia Integrada', 'tipo' => 'cnpj', 'cidade' => 'Cotia'],
    ['nome' => 'Renata Oliveira Arquitetura', 'tipo' => 'cpf', 'cidade' => 'Embu das Artes'],
  ];

  public function run(): void
  {
    $admin = User::where('email', 'admin@acesso.local')->firstOrFail();

    $this->seedDemoUsers();
    $customers = $this->seedCustomers();
    $assets = $this->seedFleet($admin);
    $this->seedPricing();
    $this->seedPartCatalog();
    $this->seedRentals($admin, $customers, $assets);
    $this->seedReceivableTitles();
    $this->seedFinancialAnalysisHistory($admin);

    $this->command?->info(sprintf(
      'Demo: %d clientes, %d patrimônios, %d locações (%d locadas).',
      Customer::count(),
      Asset::count(),
      Rental::count(),
      Rental::query()->where('status', RentalStatus::Locado->value)->count(),
    ));
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
      $user = User::firstOrCreate(
        ['email' => $profile['email']],
        [
          'name' => $profile['name'],
          'password' => Hash::make('Acesso@2026'),
          'ativo' => true,
        ],
      );

      $user->assignRole($profile['role']->value);
    }
  }

  /** @return list<Customer> */
  private function seedCustomers(): array
  {
    $customers = [];

    foreach (self::CUSTOMER_NAMES as $index => $data) {
      $doc = $data['tipo'] === 'cnpj'
        ? $this->generateValidCnpj($index + 1)
        : $this->generateValidCpf($index + 100);

      $customers[] = Customer::create([
        'nome' => $data['nome'],
        'cpf_cnpj' => $doc,
        'telefone' => sprintf('(11) 9%04d-%04d', 1000 + $index, 2000 + $index),
        'email' => 'contato'.($index + 1).'@demo-cliente.com.br',
        'endereco' => 'Rua Demo '.($index + 1).', '.$data['cidade'].' — SP',
        'ativo' => true,
        'created_by' => User::query()->where('email', 'admin@acesso.local')->value('id'),
      ]);
    }

    return $customers;
  }

  private function seedPricing(): void
  {
    $categoryPrices = [
      'Betoneira' => ['diaria' => 85, 'semanal' => 450, 'mensal' => 1400],
      'Martelete' => ['diaria' => 55, 'semanal' => 280, 'mensal' => 850],
      'Andaime' => ['diaria' => 35, 'semanal' => 180, 'mensal' => 550],
      'Gerador' => ['diaria' => 120, 'semanal' => 650, 'mensal' => 2000],
      'Outros' => ['diaria' => 70, 'semanal' => 350, 'mensal' => 1000],
    ];

    foreach (EquipmentCategory::query()->get() as $category) {
      $prices = $categoryPrices[$category->nome] ?? $categoryPrices['Outros'];

      foreach ($prices as $periodo => $valor) {
        EquipmentPricing::firstOrCreate(
          [
            'equipment_category_id' => $category->id,
            'periodo' => $periodo,
          ],
          [
            'valor' => $valor,
            'ativo' => true,
          ],
        );
      }
    }

    $modelOverrides = [
      ['marca' => 'Bosch', 'modelo' => 'GBH 2-24', 'diaria' => 65],
      ['marca' => 'Honda', 'modelo' => 'EG 6500', 'diaria' => 150],
    ];

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
        ],
        [
          'valor' => $override['diaria'],
          'ativo' => true,
        ],
      );
    }
  }

  /** @return list<Asset> */
  private function seedFleet(User $admin): array
  {
    $statusService = app(AssetStatusService::class);
    $maintenanceService = app(MaintenanceOrderService::class);
    $assets = [];
    $counter = 1;

    foreach (EquipmentCategory::query()->orderBy('nome')->get() as $category) {
      $modelDefs = self::MODELS_BY_CATEGORY[$category->nome] ?? [];

      foreach ($modelDefs as $def) {
        $model = EquipmentModel::firstOrCreate(
          [
            'equipment_category_id' => $category->id,
            'marca' => $def['marca'],
            'modelo' => $def['modelo'],
          ],
          [
            'ativo' => true,
            'especificacoes' => $def['specs'],
          ],
        );

        for ($unit = 1; $unit <= 5; $unit++) {
          $code = sprintf('PAT-%s-%03d', strtoupper(substr($category->nome, 0, 3)), $counter);

          $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'serie' => 'SN-'.now()->year.'-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'valor_compra' => 800 + ($counter * 37.5),
            'data_compra' => now()->subMonths(rand(3, 36))->toDateString(),
            'localizacao' => ['Pátio A', 'Pátio B', 'Depósito', 'Oficina'][($counter - 1) % 4],
            'observacoes' => "Patrimônio demo {$code} — {$def['marca']} {$def['modelo']}.",
          ]);

          $assets[] = $statusService->createWithInitialStatus($asset, AssetStatus::Disponivel, user: $admin);
          $counter++;
        }
      }
    }

    $this->applyFleetStatusMix($assets, $admin, $statusService, $maintenanceService);

    return $assets;
  }

  /** @param list<Asset> $assets */
  private function applyFleetStatusMix(
    array $assets,
    User $admin,
    AssetStatusService $statusService,
    MaintenanceOrderService $maintenanceService,
  ): void {
    $index = count($assets) - 1;

    for ($i = 0; $i < 8 && $index >= 0; $i++, $index--) {
      $maintenanceService->open(
        $assets[$index]->fresh(),
        'Manutenção corretiva programada (demo)',
        MaintenanceOrderType::Corretiva,
        user: $admin,
      );
    }

    for ($i = 0; $i < 4 && $index >= 0; $i++, $index--) {
      $asset = $assets[$index]->fresh();
      $maintenanceService->open(
        $asset,
        'Aguardando peça de reposição (demo)',
        MaintenanceOrderType::Corretiva,
        user: $admin,
      );
      $statusService->transition($asset->fresh(), AssetStatus::AguardandoPeca, 'Aguardando peça de reposição (demo)', $admin);
    }

    for ($i = 0; $i < 3 && $index >= 0; $i++, $index--) {
      $statusService->transition(
        $assets[$index]->fresh(),
        AssetStatus::Bloqueado,
        'Bloqueado para auditoria interna (demo)',
        $admin,
      );
    }

  }

  private function seedReceivableTitles(): void
  {
    $service = app(ReceivableTitleService::class);

    Rental::query()
      ->whereNotNull('valor_faturamento')
      ->where('valor_faturamento', '>', 0)
      ->whereDoesntHave('receivableTitles')
      ->each(function (Rental $rental) use ($service) {
        try {
          $service->generateForRental($rental);
        } catch (\InvalidArgumentException) {
          // já possui títulos ou sem valor
        }
      });

    ReceivableTitle::query()
      ->open()
      ->inRandomOrder()
      ->limit(10)
      ->get()
      ->each(function (ReceivableTitle $title, int $index) {
        if ($index < 6) {
          $title->update(['vencimento' => now()->subDays(rand(5, 85))->toDateString()]);
        }
      });

    Customer::query()->skip(5)->limit(3)->update(['limite_credito' => 15000]);
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
    ];

    foreach ($parts as $part) {
      PartCatalogItem::firstOrCreate(
        ['codigo_peca' => $part['codigo']],
        [
          'descricao' => $part['descricao'],
          'valor_unitario_padrao' => $part['valor'],
          'ativo' => true,
        ],
      );
    }
  }

  /**
   * @param  list<Customer>  $customers
   * @param  list<Asset>  $assets
   */
  private function seedRentals(User $admin, array $customers, array $assets): void
  {
    $rentalService = app(RentalService::class);
    $saidaChecked = $this->allChecked(RentalService::CHECKLIST_SAIDA);
    $retornoChecked = $this->allChecked(RentalService::CHECKLIST_RETORNO);

    $available = collect($assets)
      ->map(fn (Asset $asset) => $asset->fresh())
      ->filter(fn (Asset $asset) => $asset->status === AssetStatus::Disponivel->value)
      ->values();

    $customerCount = count($customers);
    $valorBase = [180, 250, 320, 450, 580, 720, 890, 1100, 1350, 1600];

    // 22 locações ativas (locado) — alimentam o painel
    for ($i = 0; $i < 22 && $available->isNotEmpty(); $i++) {
      $asset = $available->shift();
      $customer = $customers[$i % $customerCount];

      $rental = $rentalService->reserve(
        $asset,
        $customer,
        now()->addDays(rand(2, 21)),
        'Locação demo ativa para painel.',
        $admin,
      );

      $rental = $rentalService->checkout($rental, $saidaChecked, null, $admin);
      $rental->update([
        'valor_faturamento' => $valorBase[$i % count($valorBase)],
        'expected_return_at' => now()->addDays(rand(1, 30)),
        'checkout_at' => now()->subDays(rand(1, 14)),
      ]);
    }

    // 6 reservas pendentes
    for ($i = 0; $i < 6 && $available->isNotEmpty(); $i++) {
      $rentalService->reserve(
        $available->shift(),
        $customers[($i + 3) % $customerCount],
        now()->addDays(rand(5, 15)),
        'Reserva demo aguardando saída.',
        $admin,
      );
    }

    // 5 em inspeção (retorno registrado)
    for ($i = 0; $i < 5 && $available->isNotEmpty(); $i++) {
      $rental = $rentalService->reserve(
        $available->shift(),
        $customers[($i + 8) % $customerCount],
        now()->addDays(7),
        null,
        $admin,
      );
      $rental = $rentalService->checkout($rental, $saidaChecked, null, $admin);
      $rental->update(['checkout_at' => now()->subDays(rand(10, 20))]);
      $rentalService->registerReturn($rental, $retornoChecked, 'Retorno demo para inspeção.', $admin);
    }

    // 4 reservas canceladas
    for ($i = 0; $i < 4 && $available->isNotEmpty(); $i++) {
      $rental = $rentalService->reserve(
        $available->shift(),
        $customers[($i + 12) % $customerCount],
        now()->addDays(10),
        null,
        $admin,
      );
      $rentalService->cancel($rental, 'Cliente desistiu da reserva (demo).', $admin);
    }

    // 2 em manutenção em campo (via locação ativa)
    for ($i = 0; $i < 2 && $available->isNotEmpty(); $i++) {
      $rental = $rentalService->reserve(
        $available->shift(),
        $customers[($i + 20) % $customerCount],
        now()->addDays(10),
        'Locação com manutenção em campo (demo).',
        $admin,
      );
      $rental = $rentalService->checkout($rental, $saidaChecked, null, $admin);
      app(AssetStatusService::class)->transition(
        $rental->asset->fresh(),
        AssetStatus::EmManutencaoCampo,
        'Manutenção em campo durante locação (demo)',
        $admin,
      );
    }

    // 35 locações concluídas (histórico)
    for ($i = 0; $i < 35 && $available->isNotEmpty(); $i++) {
      $customer = $customers[($i + 5) % $customerCount];
      $asset = $available->shift();

      $rental = $rentalService->reserve(
        $asset,
        $customer,
        now()->subDays(rand(30, 90)),
        'Locação histórica demo.',
        $admin,
      );

      $completedAt = now()->subDays(1 + ($i % 89));
      $returnAt = $completedAt->copy()->subHours(rand(2, 48));
      $checkoutAt = $returnAt->copy()->subDays(rand(3, 15));

      $rental = $rentalService->checkout($rental, $saidaChecked, null, $admin);
      $rental->update(['checkout_at' => $checkoutAt]);

      $rental = $rentalService->registerReturn($rental, $retornoChecked, null, $admin);
      $rental->update(['returned_at' => $returnAt]);

      $rental = $rentalService->completeInspection($rental, false, null, $admin);
      $rental->update([
        'valor_faturamento' => $valorBase[($i + 2) % count($valorBase)],
        'completed_at' => $completedAt,
      ]);
    }
  }

  private function seedFinancialAnalysisHistory(User $admin): void
  {
    $maintenanceService = app(MaintenanceOrderService::class);
    $parts = PartCatalogItem::query()->where('ativo', true)->get();

    if ($parts->isEmpty()) {
      return;
    }

    $completedRentals = Rental::query()
      ->where('status', RentalStatus::Concluido->value)
      ->with('asset.equipmentModel')
      ->orderBy('id')
      ->get()
      ->unique('asset_id');

    $byCategory = $completedRentals->groupBy(
      fn (Rental $rental) => $rental->asset->equipmentModel->equipment_category_id,
    );

    $dayOffset = 2;

    foreach ($byCategory as $rentals) {
      foreach ($rentals->take(3) as $rental) {
        $asset = $rental->asset->fresh();

        if ($asset->status !== AssetStatus::Disponivel->value) {
          continue;
        }

        if (MaintenanceOrder::query()
          ->where('asset_id', $asset->id)
          ->where('status', '!=', MaintenanceOrderStatus::Cancelada->value)
          ->whereNull('completed_at')
          ->exists()) {
          continue;
        }

        try {
          $order = $maintenanceService->open(
            $asset,
            'Revisão pós-locação (demo análise financeira)',
            MaintenanceOrderType::Corretiva,
            user: $admin,
          );
          $maintenanceService->start($order, $admin);

          $part = $parts[$dayOffset % $parts->count()];
          $maintenanceService->addPart(
            $order,
            $part->descricao,
            (float) rand(1, 3),
            $part->codigo_peca,
            (float) $part->valor_unitario_padrao,
          );

          $maintenanceService->complete($order->fresh(), 'Concluída para demonstração.', $admin);
          $order->fresh()->update([
            'completed_at' => now()->subDays($dayOffset % 88 + 1),
          ]);

          $dayOffset += 11;
        } catch (\InvalidArgumentException) {
          continue;
        }
      }
    }
  }

  /** @param array<string, string> $template @return array<string, bool> */
  private function allChecked(array $template): array
  {
    return array_fill_keys(array_keys($template), true);
  }

  private function generateValidCpf(int $seed): string
  {
    $base = str_pad((string) (100000000 + ($seed % 899999999)), 9, '0', STR_PAD_LEFT);
    $digits = $base.$this->cpfCheckDigit($base, 10).$this->cpfCheckDigit($base.$this->cpfCheckDigit($base, 10), 11);

    if (preg_match('/^(\d)\1{10}$/', $digits)) {
      return $this->generateValidCpf($seed + 17);
    }

    return $digits;
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
