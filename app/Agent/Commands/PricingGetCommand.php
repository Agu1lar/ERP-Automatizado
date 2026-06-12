<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Enums\RentalPricingPeriod;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\User;
use App\Support\CopilotNavigationLinks;
use InvalidArgumentException;

class PricingGetCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'pricing.get';
    }

    public static function description(): string
    {
        return 'Consulta preços de uma categoria ou modelo específico (diária, semanal, mensal).';
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
                'category_id' => ['type' => 'integer'],
                'category_name' => ['type' => 'string'],
                'model_id' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        if (! empty($input['model_id'])) {
            return $this->forModel((int) $input['model_id']);
        }

        $category = $this->resolveCategory($input);

        return $this->success(
            "Preços da categoria **{$category->nome}**.",
            $this->contextBuilder->pricingCategory($category),
            [
                ['label' => 'Abrir preços', 'url' => CopilotNavigationLinks::pricing(), 'primary' => true],
            ],
        );
    }

    private function forModel(int $modelId): AgentCommandResult
    {
        $model = EquipmentModel::query()->with('category')->findOrFail($modelId);

        $prices = EquipmentPricing::query()
            ->where('equipment_model_id', $model->id)
            ->where('ativo', true)
            ->get()
            ->keyBy('periodo');

        $byPeriod = [];

        foreach (RentalPricingPeriod::cases() as $period) {
            $row = $prices->get($period->value);
            $byPeriod[$period->value] = $row ? (float) $row->valor : null;
        }

        return $this->success(
            "Preços do modelo **{$model->displayName()}**.",
            [
                'entity' => 'pricing_model',
                'model' => [
                    'id' => $model->id,
                    'nome' => $model->displayName(),
                    'category_id' => $model->equipment_category_id,
                    'category_nome' => $model->category?->nome,
                ],
                'prices' => $byPeriod,
            ],
            [
                ['label' => 'Abrir preços', 'url' => CopilotNavigationLinks::pricing(), 'primary' => true],
            ],
        );
    }

    /** @param  array<string, mixed>  $input */
    private function resolveCategory(array $input): EquipmentCategory
    {
        if (! empty($input['category_id'])) {
            return EquipmentCategory::query()->findOrFail((int) $input['category_id']);
        }

        $name = trim((string) ($input['category_name'] ?? ''));

        if ($name !== '') {
            $matches = EquipmentCategory::query()
                ->where('nome', 'like', '%'.$name.'%')
                ->orderBy('nome')
                ->limit(2)
                ->get();

            if ($matches->count() === 1) {
                return $matches->first();
            }

            if ($matches->count() > 1) {
                throw new InvalidArgumentException("Múltiplas categorias para \"{$name}\". Informe category_id.");
            }

            throw new InvalidArgumentException("Categoria não encontrada: \"{$name}\".");
        }

        throw new InvalidArgumentException('Informe category_id, category_name ou model_id.');
    }
}
