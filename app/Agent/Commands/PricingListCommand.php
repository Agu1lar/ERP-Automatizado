<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Enums\RentalPricingPeriod;
use App\Models\User;
use App\Services\EquipmentPricingService;
use App\Support\CopilotNavigationLinks;

class PricingListCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly EquipmentPricingService $pricingService,
    ) {}

    public static function name(): string
    {
        return 'pricing.list';
    }

    public static function description(): string
    {
        return 'Lista tabela de preços por categoria de equipamento (diária, semanal, mensal) e overrides por modelo.';
    }

    public function permission(): string
    {
        return 'pricing.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'q' => ['type' => 'string', 'description' => 'Filtrar por nome da categoria.'],
                'active_only' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $term = mb_strtolower(trim((string) ($input['q'] ?? '')));
        $limit = min(max((int) ($input['limit'] ?? 25), 1), 50);
        $activeOnly = (bool) ($input['active_only'] ?? true);

        $rows = $this->pricingService->categoryGrid()
            ->filter(function (array $row) use ($term, $activeOnly) {
                /** @var \App\Models\Domain\Fleet\EquipmentCategory $category */
                $category = $row['category'];

                if ($activeOnly && ! $category->ativo) {
                    return false;
                }

                if ($term === '') {
                    return true;
                }

                return str_contains(mb_strtolower($category->nome), $term);
            })
            ->take($limit)
            ->map(function (array $row) {
                /** @var \App\Models\Domain\Fleet\EquipmentCategory $category */
                $category = $row['category'];
                $prices = [];

                foreach (RentalPricingPeriod::cases() as $period) {
                    $raw = $row['prices'][$period->value] ?? '';
                    $prices[$period->value] = $raw !== '' ? (float) $raw : null;
                }

                return [
                    'category_id' => $category->id,
                    'category_nome' => $category->nome,
                    'tipo_linha' => $category->tipo_linha,
                    'ativo' => $category->ativo,
                    'prices' => $prices,
                    'model_override_count' => $row['model_override_count'],
                ];
            })
            ->values();

        $count = $rows->count();
        $message = $count === 0
            ? 'Não encontrei categorias na tabela de preços com esse filtro.'
            : "**Tabela de preços** — {$count} categoria(s).\n\nValores por diária/semana/mês; overrides por modelo aparecem na contagem.";

        return $this->success(
            $message,
            [
                'entity' => 'pricing_list',
                'count' => $count,
                'categories' => $rows->all(),
            ],
            [
                ['label' => 'Abrir tabela de preços', 'url' => CopilotNavigationLinks::pricing(), 'primary' => true],
            ],
        );
    }
}
