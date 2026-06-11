<?php



namespace App\Services;



use App\Enums\MaintenanceOrderStatus;

use App\Enums\RentalStatus;

use App\Models\Domain\Fleet\Asset;

use App\Models\Domain\Fleet\EquipmentCategory;

use App\Models\Domain\Fleet\EquipmentModel;

use App\Models\Domain\Maintenance\MaintenanceLaborHour;

use App\Models\Domain\Maintenance\MaintenancePart;

use App\Models\Domain\Rental\Rental;

use Carbon\CarbonInterface;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;



class ProfitabilityReportService

{

    private function hourlyRate(): float

    {

        return (float) config('maintenance.default_hourly_rate', 65.0);

    }



    /** @return array{faturamento: float, custo_pecas: float, custo_mao_obra: float, custo_manutencao: float, resultado: float, margem_percent: float|null, locacoes: int, os_concluidas: int, taxa_hora_mao_obra: float} */

    public function summary(CarbonInterface $from, CarbonInterface $to): array

    {

        $faturamento = $this->totalRevenue($from, $to);

        $custoPecas = $this->totalMaintenancePartsCost($from, $to);

        $custoMaoObra = $this->totalMaintenanceLaborCost($from, $to);

        $custo = round($custoPecas + $custoMaoObra, 2);

        $resultado = round($faturamento - $custo, 2);



        return [

            'faturamento' => $faturamento,

            'custo_pecas' => $custoPecas,

            'custo_mao_obra' => $custoMaoObra,

            'custo_manutencao' => $custo,

            'resultado' => $resultado,

            'margem_percent' => $faturamento > 0

                ? round(($resultado / $faturamento) * 100, 1)

                : null,

            'locacoes' => $this->completedRentalsCount($from, $to),

            'os_concluidas' => $this->completedMaintenanceOrdersCount($from, $to),

            'taxa_hora_mao_obra' => $this->hourlyRate(),

        ];

    }



    /**

     * @return Collection<int, object{

     *     grupo_id: int|string,

     *     grupo_nome: string,

     *     faturamento: float,

     *     custo_manutencao: float,

     *     custo_pecas: float,

     *     custo_mao_obra: float,

     *     resultado: float,

     *     locacoes: int,

     *     os_concluidas: int

     * }>

     */

    public function byCategory(CarbonInterface $from, CarbonInterface $to): Collection

    {

        $revenue = $this->revenueGroupedByCategory($from, $to);

        $partsCosts = $this->maintenancePartsCostGroupedByCategory($from, $to);

        $laborCosts = $this->maintenanceLaborCostGroupedByCategory($from, $to);



        $ids = $revenue->keys()->merge($partsCosts->keys())->merge($laborCosts->keys())->unique();

        $names = EquipmentCategory::query()->whereIn('id', $ids)->pluck('nome', 'id');



        return $ids->map(function ($id) use ($revenue, $partsCosts, $laborCosts, $names) {

            $fat = (float) ($revenue[$id]['faturamento'] ?? 0);

            $pecas = (float) ($partsCosts[$id]['custo'] ?? 0);

            $maoObra = (float) ($laborCosts[$id]['custo'] ?? 0);

            $custo = round($pecas + $maoObra, 2);



            return (object) [

                'grupo_id' => $id,

                'grupo_nome' => $names[$id] ?? '—',

                'faturamento' => $fat,

                'custo_pecas' => $pecas,

                'custo_mao_obra' => $maoObra,

                'custo_manutencao' => $custo,

                'resultado' => round($fat - $custo, 2),

                'locacoes' => (int) ($revenue[$id]['locacoes'] ?? 0),

                'os_concluidas' => max(

                    (int) ($partsCosts[$id]['os'] ?? 0),

                    (int) ($laborCosts[$id]['os'] ?? 0),

                ),

            ];

        })->sortByDesc('faturamento')->values();

    }



    /**

     * @return Collection<int, object{

     *     grupo_id: int,

     *     grupo_nome: string,

     *     faturamento: float,

     *     custo_manutencao: float,

     *     custo_pecas: float,

     *     custo_mao_obra: float,

     *     resultado: float,

     *     locacoes: int,

     *     os_concluidas: int

     * }>

     */

    public function byAsset(CarbonInterface $from, CarbonInterface $to, int $limit = 100): Collection

    {

        $revenue = $this->revenueGroupedByAsset($from, $to);

        $partsCosts = $this->maintenancePartsCostGroupedByAsset($from, $to);

        $laborCosts = $this->maintenanceLaborCostGroupedByAsset($from, $to);



        $ids = $revenue->keys()->merge($partsCosts->keys())->merge($laborCosts->keys())->unique()->take($limit);

        $assets = Asset::query()

            ->with('equipmentModel.category')

            ->whereIn('id', $ids)

            ->get()

            ->keyBy('id');



        return $ids->map(function ($id) use ($revenue, $partsCosts, $laborCosts, $assets) {

            $asset = $assets[$id] ?? null;

            $fat = (float) ($revenue[$id]['faturamento'] ?? 0);

            $pecas = (float) ($partsCosts[$id]['custo'] ?? 0);

            $maoObra = (float) ($laborCosts[$id]['custo'] ?? 0);

            $custo = round($pecas + $maoObra, 2);



            return (object) [

                'grupo_id' => (int) $id,

                'grupo_nome' => $asset

                    ? $asset->codigo_patrimonio.' — '.($asset->equipmentModel?->displayName() ?? '')

                    : 'Patrimônio #'.$id,

                'faturamento' => $fat,

                'custo_pecas' => $pecas,

                'custo_mao_obra' => $maoObra,

                'custo_manutencao' => $custo,

                'resultado' => round($fat - $custo, 2),

                'locacoes' => (int) ($revenue[$id]['locacoes'] ?? 0),

                'os_concluidas' => max(

                    (int) ($partsCosts[$id]['os'] ?? 0),

                    (int) ($laborCosts[$id]['os'] ?? 0),

                ),

            ];

        })->sortByDesc('resultado')->values();

    }



    public function totalRevenue(CarbonInterface $from, CarbonInterface $to): float

    {

        return (float) Rental::query()

            ->where('status', RentalStatus::Concluido->value)

            ->whereNotNull('completed_at')

            ->whereDate('completed_at', '>=', $from->toDateString())

            ->whereDate('completed_at', '<=', $to->toDateString())

            ->sum('valor_faturamento');

    }



    public function totalMaintenancePartsCost(CarbonInterface $from, CarbonInterface $to): float

    {

        return (float) MaintenancePart::query()

            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_parts.maintenance_order_id')

            ->where('maintenance_orders.status', MaintenanceOrderStatus::Concluida->value)

            ->whereNotNull('maintenance_orders.completed_at')

            ->whereDate('maintenance_orders.completed_at', '>=', $from->toDateString())

            ->whereDate('maintenance_orders.completed_at', '<=', $to->toDateString())

            ->selectRaw('COALESCE(SUM(maintenance_parts.valor_unitario * maintenance_parts.quantidade), 0) as total')

            ->value('total');

    }



    public function totalMaintenanceLaborCost(CarbonInterface $from, CarbonInterface $to): float

    {

        $hours = (float) MaintenanceLaborHour::query()

            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_labor_hours.maintenance_order_id')

            ->where('maintenance_orders.status', MaintenanceOrderStatus::Concluida->value)

            ->whereNotNull('maintenance_orders.completed_at')

            ->whereDate('maintenance_orders.completed_at', '>=', $from->toDateString())

            ->whereDate('maintenance_orders.completed_at', '<=', $to->toDateString())

            ->sum('maintenance_labor_hours.horas');



        return round($hours * $this->hourlyRate(), 2);

    }



    private function completedRentalsCount(CarbonInterface $from, CarbonInterface $to): int

    {

        return Rental::query()

            ->where('status', RentalStatus::Concluido->value)

            ->whereNotNull('completed_at')

            ->whereDate('completed_at', '>=', $from->toDateString())

            ->whereDate('completed_at', '<=', $to->toDateString())

            ->count();

    }



    private function completedMaintenanceOrdersCount(CarbonInterface $from, CarbonInterface $to): int

    {

        return \App\Models\Domain\Maintenance\MaintenanceOrder::query()

            ->where('status', MaintenanceOrderStatus::Concluida->value)

            ->whereNotNull('completed_at')

            ->whereDate('completed_at', '>=', $from->toDateString())

            ->whereDate('completed_at', '<=', $to->toDateString())

            ->count();

    }



    /** @return Collection<int|string, array{faturamento: float, locacoes: int}> */

    private function revenueGroupedByCategory(CarbonInterface $from, CarbonInterface $to): Collection

    {

        return Rental::query()

            ->join('assets', 'rentals.asset_id', '=', 'assets.id')

            ->join('equipment_models', 'assets.equipment_model_id', '=', 'equipment_models.id')

            ->where('rentals.status', RentalStatus::Concluido->value)

            ->whereNotNull('rentals.completed_at')

            ->whereDate('rentals.completed_at', '>=', $from->toDateString())

            ->whereDate('rentals.completed_at', '<=', $to->toDateString())

            ->groupBy('equipment_models.equipment_category_id')

            ->select([

                'equipment_models.equipment_category_id as category_id',

                DB::raw('COALESCE(SUM(rentals.valor_faturamento), 0) as faturamento'),

                DB::raw('COUNT(rentals.id) as locacoes'),

            ])

            ->get()

            ->mapWithKeys(fn ($row) => [

                (int) $row->category_id => [

                    'faturamento' => (float) $row->faturamento,

                    'locacoes' => (int) $row->locacoes,

                ],

            ]);

    }



    /** @return Collection<int|string, array{custo: float, os: int}> */

    private function maintenancePartsCostGroupedByCategory(CarbonInterface $from, CarbonInterface $to): Collection

    {

        return MaintenancePart::query()

            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_parts.maintenance_order_id')

            ->join('assets', 'maintenance_orders.asset_id', '=', 'assets.id')

            ->join('equipment_models', 'assets.equipment_model_id', '=', 'equipment_models.id')

            ->where('maintenance_orders.status', MaintenanceOrderStatus::Concluida->value)

            ->whereNotNull('maintenance_orders.completed_at')

            ->whereDate('maintenance_orders.completed_at', '>=', $from->toDateString())

            ->whereDate('maintenance_orders.completed_at', '<=', $to->toDateString())

            ->groupBy('equipment_models.equipment_category_id')

            ->select([

                'equipment_models.equipment_category_id as category_id',

                DB::raw('COALESCE(SUM(maintenance_parts.valor_unitario * maintenance_parts.quantidade), 0) as custo'),

                DB::raw('COUNT(DISTINCT maintenance_orders.id) as os'),

            ])

            ->get()

            ->mapWithKeys(fn ($row) => [

                (int) $row->category_id => [

                    'custo' => (float) $row->custo,

                    'os' => (int) $row->os,

                ],

            ]);

    }



    /** @return Collection<int|string, array{custo: float, os: int}> */

    private function maintenanceLaborCostGroupedByCategory(CarbonInterface $from, CarbonInterface $to): Collection

    {

        $rate = $this->hourlyRate();



        return MaintenanceLaborHour::query()

            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_labor_hours.maintenance_order_id')

            ->join('assets', 'maintenance_orders.asset_id', '=', 'assets.id')

            ->join('equipment_models', 'assets.equipment_model_id', '=', 'equipment_models.id')

            ->where('maintenance_orders.status', MaintenanceOrderStatus::Concluida->value)

            ->whereNotNull('maintenance_orders.completed_at')

            ->whereDate('maintenance_orders.completed_at', '>=', $from->toDateString())

            ->whereDate('maintenance_orders.completed_at', '<=', $to->toDateString())

            ->groupBy('equipment_models.equipment_category_id')

            ->select([

                'equipment_models.equipment_category_id as category_id',

                DB::raw('COALESCE(SUM(maintenance_labor_hours.horas), 0) as horas'),

                DB::raw('COUNT(DISTINCT maintenance_orders.id) as os'),

            ])

            ->get()

            ->mapWithKeys(fn ($row) => [

                (int) $row->category_id => [

                    'custo' => round((float) $row->horas * $rate, 2),

                    'os' => (int) $row->os,

                ],

            ]);

    }



    /** @return Collection<int|string, array{faturamento: float, locacoes: int}> */

    private function revenueGroupedByAsset(CarbonInterface $from, CarbonInterface $to): Collection

    {

        return Rental::query()

            ->where('status', RentalStatus::Concluido->value)

            ->whereNotNull('completed_at')

            ->whereDate('completed_at', '>=', $from->toDateString())

            ->whereDate('completed_at', '<=', $to->toDateString())

            ->groupBy('asset_id')

            ->select([

                'asset_id',

                DB::raw('COALESCE(SUM(valor_faturamento), 0) as faturamento'),

                DB::raw('COUNT(id) as locacoes'),

            ])

            ->get()

            ->mapWithKeys(fn ($row) => [

                (int) $row->asset_id => [

                    'faturamento' => (float) $row->faturamento,

                    'locacoes' => (int) $row->locacoes,

                ],

            ]);

    }



    /** @return Collection<int|string, array{custo: float, os: int}> */

    private function maintenancePartsCostGroupedByAsset(CarbonInterface $from, CarbonInterface $to): Collection

    {

        return MaintenancePart::query()

            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_parts.maintenance_order_id')

            ->where('maintenance_orders.status', MaintenanceOrderStatus::Concluida->value)

            ->whereNotNull('maintenance_orders.completed_at')

            ->whereDate('maintenance_orders.completed_at', '>=', $from->toDateString())

            ->whereDate('maintenance_orders.completed_at', '<=', $to->toDateString())

            ->groupBy('maintenance_orders.asset_id')

            ->select([

                'maintenance_orders.asset_id',

                DB::raw('COALESCE(SUM(maintenance_parts.valor_unitario * maintenance_parts.quantidade), 0) as custo'),

                DB::raw('COUNT(DISTINCT maintenance_orders.id) as os'),

            ])

            ->get()

            ->mapWithKeys(fn ($row) => [

                (int) $row->asset_id => [

                    'custo' => (float) $row->custo,

                    'os' => (int) $row->os,

                ],

            ]);

    }



    /** @return Collection<int|string, array{custo: float, os: int}> */

    private function maintenanceLaborCostGroupedByAsset(CarbonInterface $from, CarbonInterface $to): Collection

    {

        $rate = $this->hourlyRate();



        return MaintenanceLaborHour::query()

            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_labor_hours.maintenance_order_id')

            ->where('maintenance_orders.status', MaintenanceOrderStatus::Concluida->value)

            ->whereNotNull('maintenance_orders.completed_at')

            ->whereDate('maintenance_orders.completed_at', '>=', $from->toDateString())

            ->whereDate('maintenance_orders.completed_at', '<=', $to->toDateString())

            ->groupBy('maintenance_orders.asset_id')

            ->select([

                'maintenance_orders.asset_id',

                DB::raw('COALESCE(SUM(maintenance_labor_hours.horas), 0) as horas'),

                DB::raw('COUNT(DISTINCT maintenance_orders.id) as os'),

            ])

            ->get()

            ->mapWithKeys(fn ($row) => [

                (int) $row->asset_id => [

                    'custo' => round((float) $row->horas * $rate, 2),

                    'os' => (int) $row->os,

                ],

            ]);

    }

}


