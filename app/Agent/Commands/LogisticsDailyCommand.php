<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Enums\LogisticsShift;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Support\CopilotNavigationLinks;
use App\Support\LogisticsDailyQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LogisticsDailyCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly LogisticsDailyQuery $dailyQuery,
    ) {}

    public static function name(): string
    {
        return 'logistics.daily';
    }

    public static function description(): string
    {
        return 'Lista do dia logístico: entregas, retiradas, movimentações no pátio e retornos previstos.';
    }

    public function permission(): string
    {
        return 'rentals.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Data da lista. Padrão: hoje.',
                ],
                'section' => [
                    'type' => 'string',
                    'enum' => [
                        'all',
                        'entregas',
                        'cliente_retira',
                        'retiradas',
                        'cliente_devolve',
                        'retornos_previstos',
                    ],
                    'description' => 'Filtrar uma seção específica. Padrão: all.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                    'description' => 'Máximo de itens por seção retornados.',
                ],
                'region' => [
                    'type' => 'string',
                    'enum' => ['bh', 'rmbh', 'interior', 'indefinido'],
                    'description' => 'Filtrar por região da obra (BH, RMBH, interior MG).',
                ],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $date = ! empty($input['date'])
            ? Carbon::parse($input['date'])->startOfDay()
            : now()->startOfDay();
        $section = $input['section'] ?? 'all';
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);
        $region = $input['region'] ?? null;

        $counts = $this->dailyQuery->countsForDate($date, $region);
        $sections = $this->buildSections($date, $section, $limit, $region);

        $totalItems = array_sum(array_map(fn (array $s) => $s['count'], $sections));

        $message = '**Lista do dia** — '.$date->translatedFormat('d/m/Y')."\n\n"
            ."• Entregas (frota): **{$counts['entregas']}**\n"
            ."• Cliente retira: **{$counts['cliente_retira']}**\n"
            ."• Recolhidas (frota): **{$counts['retiradas']}**\n"
            ."• Cliente devolve: **{$counts['cliente_devolve']}**\n"
            ."• Retornos s/ agenda: **{$counts['retornos_previstos']}**\n\n";

        if ($totalItems === 0) {
            $message .= 'Nenhuma movimentação logística nesta data com os filtros informados.';
        } else {
            $message .= "Detalhes abaixo (até {$limit} por seção). Abra a tela completa para imprimir ou navegar.";
        }

        return $this->success(
            $message,
            [
                'entity' => 'logistics_daily',
                'date' => $date->toDateString(),
                'counts' => $counts,
                'sections' => $sections,
            ],
            [
                [
                    'label' => 'Abrir lista do dia',
                    'url' => CopilotNavigationLinks::logisticsDaily($date->toDateString()),
                    'primary' => true,
                ],
                [
                    'label' => 'Mapa de obras ativas',
                    'url' => CopilotNavigationLinks::activeWorksMap($region),
                ],
            ],
        );
    }

    /** @return list<array{key: string, label: string, count: int, items: list<array<string, mixed>>}> */
    private function buildSections(Carbon $date, string $section, int $limit, ?string $region = null): array
    {
        $all = [
            'entregas' => [
                'label' => 'Entregas pela frota',
                'kind' => 'entrega',
                'rows' => $this->dailyQuery->scheduledDeliveries($date, $region),
            ],
            'cliente_retira' => [
                'label' => 'Cliente retira no pátio',
                'kind' => 'cliente_retira',
                'rows' => $this->dailyQuery->customerPickupsAtYard($date, $region),
            ],
            'retiradas' => [
                'label' => 'Recolhidas pela frota',
                'kind' => 'retirada',
                'rows' => $this->dailyQuery->scheduledPickups($date, $region),
            ],
            'cliente_devolve' => [
                'label' => 'Cliente devolve no pátio',
                'kind' => 'cliente_devolve',
                'rows' => $this->dailyQuery->customerReturnsAtYard($date, $region),
            ],
            'retornos_previstos' => [
                'label' => 'Retornos previstos sem agenda',
                'kind' => 'retorno',
                'rows' => $this->dailyQuery->expectedReturnsWithoutPickupSchedule($date, $region),
            ],
        ];

        $selected = $section === 'all'
            ? $all
            : array_intersect_key($all, [$section => true]);

        $result = [];

        foreach ($selected as $key => $meta) {
            /** @var Collection<int, Rental> $rows */
            $rows = $meta['rows'];

            $result[] = [
                'key' => $key,
                'label' => $meta['label'],
                'count' => $rows->count(),
                'items' => $rows->take($limit)
                    ->map(fn (Rental $rental) => $this->mapRental($rental, $meta['kind']))
                    ->values()
                    ->all(),
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function mapRental(Rental $rental, string $kind): array
    {
        $turno = match ($kind) {
            'entrega', 'cliente_retira' => $rental->entrega_turno,
            'retirada', 'cliente_devolve' => $rental->retirada_turno,
            default => null,
        };

        $observacoes = match ($kind) {
            'entrega', 'cliente_retira' => $rental->entrega_observacoes,
            'retirada', 'cliente_devolve' => $rental->retirada_observacoes,
            default => $rental->observacoes,
        };

        $shift = $turno ? LogisticsShift::tryFrom($turno) : null;

        return [
            'rental_id' => $rental->id,
            'rental_codigo' => $rental->codigo,
            'status' => $rental->status,
            'turno' => $shift?->label(),
            'customer_nome' => $rental->customer?->nome,
            'asset_codigo' => $rental->asset?->codigo_patrimonio,
            'yard_origem' => $rental->asset?->yard?->displayLabel(),
            'local_obra' => $rental->local_obra,
            'observacoes' => $observacoes,
            'rental_url' => route('rentals.show', $rental),
        ];
    }
}
