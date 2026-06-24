<?php

namespace App\Agent\Commands;

use App\Enums\RentalStatus;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Support\CopilotNavigationLinks;
use App\Support\EquipmentCategoryResolver;

class RentalListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'rental.list';
    }

    public static function description(): string
    {
        return 'Lista locações/contratos com filtros. Para "N contratos mais recentes" use limit e sort=recent (sem status).';
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
                'status' => ['type' => 'string', 'enum' => array_column(RentalStatus::cases(), 'value')],
                'category_id' => ['type' => 'integer'],
                'category_name' => ['type' => 'string'],
                'category_query' => ['type' => 'string'],
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                'sort' => ['type' => 'string', 'description' => 'recent (cadastro mais novo) ou updated (última alteração).'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 20), 1), 50);
        $sort = (string) ($input['sort'] ?? 'updated');
        $category = $this->resolveCategory($input);
        $categoryQuery = ! empty($input['category_query']) ? (string) $input['category_query'] : null;
        $status = ! empty($input['status']) ? (string) $input['status'] : null;

        if (! $category && $categoryQuery) {
            $label = EquipmentCategoryResolver::labelForTerm($categoryQuery) ?? $categoryQuery;

            return $this->success(
                "**Não encontrei a categoria \"{$label}\"** cadastrada na empresa ativa.\n\n"
                ."Por isso **não listei outras locações** (evita confundir com escavadeiras, etc.).\n\n"
                .'Cadastre a categoria em **Frota → Categorias** ou rode o seeder de demo com betoneiras.',
                [
                    'entity' => 'rental_list',
                    'count' => 0,
                    'category_unresolved' => $categoryQuery,
                ],
                [
                    ['label' => 'Ver categorias de equipamento', 'url' => route('fleet.categories.index'), 'primary' => true],
                    ['label' => 'Ver locações', 'url' => route('rentals.index')],
                ],
            );
        }

        $rentals = Rental::query()
            ->with(['customer', 'asset.equipmentModel.category'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($category, function ($q) use ($category) {
                $q->whereHas('asset.equipmentModel', fn ($mq) => $mq->where('equipment_category_id', $category->id));
            })
            ->when(! empty($input['q']), function ($q) use ($input) {
                $term = trim((string) $input['q']);
                $q->where(function ($q) use ($term) {
                    $q->where('codigo', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', fn ($c) => $c->where('nome', 'like', '%'.$term.'%'))
                        ->orWhereHas('asset', fn ($a) => $a->where('codigo_patrimonio', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc($sort === 'recent' ? 'created_at' : 'updated_at')
            ->limit($limit)
            ->get();

        $count = $rentals->count();
        $statusLabel = $status ? RentalStatus::from($status)->label() : null;
        $categoryLabel = $category?->nome;

        $message = $this->buildGuidanceMessage($count, $statusLabel, $categoryLabel, $rentals);

        $panelUrl = CopilotNavigationLinks::rentalsPanel([
            'status_scope' => $status === RentalStatus::Locado->value ? 'locado' : ($status ?: 'locado'),
            'category_id' => $category?->id,
            'search' => $input['q'] ?? null,
        ]);

        $nextSteps = [
            [
                'label' => 'Abrir painel com este filtro',
                'url' => $panelUrl,
                'primary' => true,
            ],
        ];

        if ($count > 0 && $count <= 10) {
            foreach ($rentals as $rental) {
                $nextSteps[] = [
                    'label' => "Ver {$rental->codigo}",
                    'url' => route('rentals.show', $rental),
                ];
            }
        }

        if ($count === 0) {
            $nextSteps[] = [
                'label' => 'Ver todas as locações',
                'url' => route('rentals.index'),
            ];
        }

        return $this->success(
            $message,
            [
                'entity' => 'rental_list',
                'count' => $count,
                'filters' => [
                    'status' => $status,
                    'category_id' => $category?->id,
                    'category_name' => $categoryLabel,
                    'q' => $input['q'] ?? null,
                ],
                'panel_url' => $panelUrl,
                'rentals' => $rentals->map(fn (Rental $r) => [
                    'id' => $r->id,
                    'codigo' => $r->codigo,
                    'status' => $r->status,
                    'status_label' => $r->statusEnum()->label(),
                    'customer_nome' => $r->customer?->nome,
                    'asset_codigo' => $r->asset?->codigo_patrimonio,
                    'category_nome' => $r->asset?->equipmentModel?->category?->nome,
                    'expected_return_at' => $r->expected_return_at?->toDateString(),
                ])->all(),
            ],
            $nextSteps,
        );
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
            return EquipmentCategoryResolver::resolveFromText((string) $input['category_query']);
        }

        return null;
    }

    /** @param  \Illuminate\Support\Collection<int, Rental>  $rentals */
    private function buildGuidanceMessage(int $count, ?string $statusLabel, ?string $categoryLabel, $rentals): string
    {
        $parts = [];

        if ($count === 0) {
            $parts[] = 'Não encontrei locações com esse filtro.';
            if ($categoryLabel) {
                $parts[] = " (categoria **{$categoryLabel}**)";
            }
            $parts[] = '.';
        } else {
            $parts[] = "Encontrei **{$count}** locação(ões)";
            if ($statusLabel) {
                $parts[] = " com status **{$statusLabel}**";
            }
            if ($categoryLabel) {
                $parts[] = " de **{$categoryLabel}**";
            }
            $parts[] = '.';
        }

        $parts[] = "\n\nUse o botão abaixo para abrir o **painel de locações** já filtrado.";
        $parts[] = ' Se quiser que eu **execute** algo (saída, retorno, faturar), diga o código LOC-… ou confirme quando eu pedir.';

        if ($count > 0 && $count <= 10) {
            $parts[] = "\n\n**Resumo:**";
            foreach ($rentals as $rental) {
                $equip = $rental->asset?->equipmentDisplayName() ?? '—';
                $cat = $rental->asset?->equipmentModel?->category?->nome ?? '—';
                $parts[] = "\n• {$rental->codigo} — {$equip} ({$cat}) — {$rental->customer?->nome}";
            }
        }

        return implode('', $parts);
    }
}
