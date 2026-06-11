<?php

namespace App\Agent\Commands;

use App\Enums\RentalStatus;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Support\CopilotNavigationLinks;
use App\Support\EquipmentCategoryResolver;
use Carbon\Carbon;

class RentalStatsCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'rental.stats';
    }

    public static function description(): string
    {
        return 'Conta locações por categoria de equipamento em um período (saídas/registros no intervalo).';
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
                'category_id' => ['type' => 'integer'],
                'category_name' => ['type' => 'string'],
                'category_query' => ['type' => 'string'],
                'date_from' => ['type' => 'string', 'format' => 'date'],
                'date_to' => ['type' => 'string', 'format' => 'date'],
                'status' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        [$from, $to] = $this->resolvePeriod($input);
        $category = $this->resolveCategory($input);
        $categoryQuery = $input['category_query'] ?? null;
        $categoryLabel = $category?->nome ?? EquipmentCategoryResolver::labelForTerm($categoryQuery);

        if ($categoryQuery && ! $category) {
            return $this->success(
                "**Não encontrei a categoria \"{$categoryLabel}\"** cadastrada na empresa ativa.\n\n"
                .'Cadastre em **Frota → Categorias** ou informe outro tipo de equipamento.',
                [
                    'entity' => 'rental_stats',
                    'count' => 0,
                    'category_unresolved' => $categoryQuery,
                ],
                [
                    ['label' => 'Ver categorias', 'url' => route('fleet.categories.index'), 'primary' => true],
                ],
            );
        }

        $query = Rental::query()
            ->when($category, function ($q) use ($category) {
                $q->whereHas('asset.equipmentModel', fn ($mq) => $mq->where('equipment_category_id', $category->id));
            })
            ->when(! empty($input['status']), fn ($q) => $q->where('status', $input['status']))
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('checkout_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orWhereBetween('reserved_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
            });

        $count = (clone $query)->count();
        $currentlyOut = (clone $query)->where('status', RentalStatus::Locado->value)->count();

        $periodLabel = $from->format('d/m/Y').' a '.$to->format('d/m/Y');
        $equipLabel = $categoryLabel ?? 'todos os equipamentos';

        $message = "**{$count}** locação(ões) de **{$equipLabel}** com movimentação entre **{$periodLabel}**.\n"
            ."• Em campo agora (locado): **{$currentlyOut}**\n\n"
            .'Peça para **listar** ou **filtrar** se quiser ver os contratos individualmente.';

        return $this->success(
            $message,
            [
                'entity' => 'rental_stats',
                'count' => $count,
                'currently_rented' => $currentlyOut,
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'category_id' => $category?->id,
                'category_name' => $categoryLabel,
            ],
            [
                [
                    'label' => 'Abrir painel filtrado',
                    'url' => CopilotNavigationLinks::rentalsPanel([
                        'status_scope' => 'locado',
                        'category_id' => $category?->id,
                    ]),
                    'primary' => true,
                ],
                [
                    'label' => 'Ver locações (lista)',
                    'url' => CopilotNavigationLinks::rentalsList(['status' => RentalStatus::Locado->value]),
                ],
            ],
        );
    }

    /** @param  array<string, mixed>  $input @return array{0: Carbon, 1: Carbon} */
    private function resolvePeriod(array $input): array
    {
        if (! empty($input['date_from']) && ! empty($input['date_to'])) {
            return [Carbon::parse($input['date_from']), Carbon::parse($input['date_to'])];
        }

        return [now()->subDays(30)->startOfDay(), now()->endOfDay()];
    }

    /** @param  array<string, mixed>  $input */
    private function resolveCategory(array $input): ?EquipmentCategory
    {
        if (! empty($input['category_id'])) {
            return EquipmentCategory::query()->find((int) $input['category_id']);
        }

        if (! empty($input['category_name'])) {
            return EquipmentCategory::query()
                ->where('nome', 'like', '%'.trim((string) $input['category_name']).'%')
                ->where('ativo', true)
                ->first();
        }

        if (! empty($input['category_query'])) {
            return EquipmentCategoryResolver::resolveFromText((string) $input['category_query'])
                ?? EquipmentCategoryResolver::resolveFromText(
                    EquipmentCategoryResolver::labelForTerm((string) $input['category_query']) ?? '',
                );
        }

        return null;
    }
}
