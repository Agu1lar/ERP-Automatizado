<?php

namespace App\Agent\Commands;

use App\Enums\AssetStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\User;
use App\Support\CopilotNavigationLinks;
use App\Support\EquipmentCategoryResolver;
use App\Support\TextSearch;

class AssetListCommand extends AbstractReadAgentCommand
{
    public static function name(): string
    {
        return 'asset.list';
    }

    public static function description(): string
    {
        return 'Lista patrimônios/equipamentos com filtros por status, categoria e busca textual.';
    }

    public function permission(): string
    {
        return 'fleet.assets.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => array_column(AssetStatus::cases(), 'value')],
                'category_id' => ['type' => 'integer'],
                'category_name' => ['type' => 'string'],
                'category_query' => ['type' => 'string'],
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 20), 1), 50);
        $category = $this->resolveCategory($input);
        $categoryQuery = ! empty($input['category_query']) ? (string) $input['category_query'] : null;
        $status = ! empty($input['status']) ? (string) $input['status'] : null;
        $search = trim((string) ($input['q'] ?? ''));

        if (! $category && $categoryQuery) {
            $label = EquipmentCategoryResolver::labelForTerm($categoryQuery) ?? $categoryQuery;

            return $this->success(
                "**Não encontrei a categoria \"{$label}\"** cadastrada.\n\n"
                .'Cadastre em **Frota → Categorias** ou ajuste o filtro.',
                ['entity' => 'asset_list', 'count' => 0, 'category_unresolved' => $categoryQuery],
                [
                    ['label' => 'Ver patrimônios', 'url' => CopilotNavigationLinks::assets(), 'primary' => true],
                ],
            );
        }

        $query = Asset::query()
            ->with(['equipmentModel.category', 'yard'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($category, fn ($q) => $q->whereHas(
                'equipmentModel',
                fn ($mq) => $mq->where('equipment_category_id', $category->id),
            ))
            ->orderBy('codigo_patrimonio');

        $assets = $query->get();

        if ($search !== '') {
            $assets = $assets->filter(fn (Asset $asset) => TextSearch::matchesAny(
                $search,
                $asset->codigo_patrimonio,
                $asset->serie,
                $asset->localizacao,
                $asset->equipmentModel?->marca,
                $asset->equipmentModel?->modelo,
                $asset->equipmentModel?->category?->nome,
            ));
        }

        $assets = $assets->take($limit)->values();
        $count = $assets->count();
        $statusLabel = $status ? AssetStatus::from($status)->label() : null;
        $categoryLabel = $category?->nome;

        $message = $count === 0
            ? 'Não encontrei patrimônios com esse filtro.'
            : "Encontrei **{$count}** patrimônio(s)"
                .($statusLabel ? " com status **{$statusLabel}**" : '')
                .($categoryLabel ? " de **{$categoryLabel}**" : '')
                .'.';

        $panelUrl = CopilotNavigationLinks::assets($search ?: null);
        $nextSteps = [
            ['label' => 'Abrir patrimônios', 'url' => $panelUrl, 'primary' => true],
        ];

        if ($count > 0 && $count <= 5) {
            foreach ($assets as $asset) {
                $nextSteps[] = [
                    'label' => "Ver {$asset->codigo_patrimonio}",
                    'url' => route('assets.show', $asset),
                ];
            }
        }

        return $this->success(
            $message,
            [
                'entity' => 'asset_list',
                'count' => $count,
                'filters' => [
                    'status' => $status,
                    'category_id' => $category?->id,
                    'category_name' => $categoryLabel,
                    'q' => $search ?: null,
                ],
                'assets' => $assets->map(fn (Asset $asset) => [
                    'id' => $asset->id,
                    'codigo_patrimonio' => $asset->codigo_patrimonio,
                    'status' => $asset->status,
                    'status_label' => $asset->statusEnum()->label(),
                    'equipamento' => $asset->equipmentDisplayName(),
                    'categoria' => $asset->equipmentModel?->category?->nome,
                    'localizacao' => $asset->localizacao,
                    'yard' => $asset->yard?->nome,
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
}
