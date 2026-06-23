<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\LogisticsDeliveryMode;
use App\Enums\LogisticsReturnMode;
use App\Enums\LogisticsShift;
use App\Enums\RentalStatus;
use App\Models\User;
use App\Services\RentalBillingService;
use App\Services\RentalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class RentalUpdateCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    private const FIELDS = [
        'local_obra', 'observacoes', 'expected_return_at', 'valor_faturamento',
        'valor_frete_entrega', 'valor_frete_recolhida', 'ficha_descricao',
        'horimetro_saida', 'horimetro_retorno',
        'entrega_modalidade', 'entrega_agendada_em', 'entrega_turno', 'entrega_observacoes',
        'retirada_modalidade', 'retirada_agendada_em', 'retirada_turno',         'retirada_observacoes',
        'contrato_clausula_prorata',
    ];

    public function __construct(
        private readonly RentalService $rentalService,
        private readonly RentalBillingService $billingService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'rental.update';
    }

    public static function description(): string
    {
        return 'Atualiza campos da ficha de locação (obra, valores, logística, horímetros, previsão de retorno).';
    }

    public function permission(): string
    {
        return 'rentals.operate';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        return $this->affectedResourcesForRental($input);
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['rental_id', 'rental_codigo'],
            ],
            'properties' => [
                'rental_id' => ['type' => 'integer'],
                'rental_codigo' => ['type' => 'string'],
                'local_obra' => ['type' => 'string'],
                'observacoes' => ['type' => 'string'],
                'expected_return_at' => ['type' => 'string', 'format' => 'date'],
                'valor_faturamento' => ['type' => 'number'],
                'valor_frete_entrega' => ['type' => 'number'],
                'valor_frete_recolhida' => ['type' => 'number'],
                'ficha_descricao' => ['type' => 'string'],
                'horimetro_saida' => ['type' => 'number'],
                'horimetro_retorno' => ['type' => 'number'],
                'entrega_modalidade' => ['type' => 'string', 'enum' => ['empresa_entrega', 'cliente_retira']],
                'entrega_agendada_em' => ['type' => 'string', 'format' => 'date'],
                'entrega_turno' => ['type' => 'string', 'enum' => ['manha', 'tarde', 'combinar']],
                'entrega_observacoes' => ['type' => 'string'],
                'retirada_modalidade' => ['type' => 'string', 'enum' => ['empresa_recolhe', 'cliente_devolve']],
                'retirada_agendada_em' => ['type' => 'string', 'format' => 'date'],
                'retirada_turno' => ['type' => 'string', 'enum' => ['manha', 'tarde', 'combinar']],
                'retirada_observacoes' => ['type' => 'string'],
                'contrato_clausula_prorata' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $changes = $this->buildChanges($input);

        if ($changes === []) {
            return $this->failure('Informe ao menos um campo para atualizar.', 'validation_failed');
        }

        if (isset($changes['local_obra'])) {
            $rental = $this->rentalService->updateLocalObra($rental, $changes['local_obra'], $user);
            unset($changes['local_obra']);
        }

        if (isset($changes['expected_return_at']) && $rental->statusEnum() === RentalStatus::Reservado) {
            $changes['expected_return_at'] = Carbon::parse($changes['expected_return_at'])->toDateString();
        } elseif (isset($changes['expected_return_at'])) {
            unset($changes['expected_return_at']);
        }

        if ($changes !== []) {
            $rental->update($changes);

            if (array_key_exists('valor_faturamento', $changes)) {
                $this->billingService->syncContractRateFromRental($rental->fresh());
            }
        }

        $rental = $rental->fresh();

        return $this->success(
            "Ficha **{$rental->codigo}** atualizada.",
            $this->contextBuilder->rental($rental),
            [['label' => 'Abrir locação', 'url' => route('rentals.show', $rental), 'primary' => true]],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $changes = $this->buildChanges($input);

        if ($changes === []) {
            return $this->failure('Informe ao menos um campo para atualizar.', 'validation_failed');
        }

        return AgentCommandResult::preview(
            'Simulação: atualizar **'.$rental->codigo.'** — '.implode(', ', array_keys($changes)).'.',
            ['rental_codigo' => $rental->codigo, 'changes' => array_keys($changes)],
        );
    }

    /** @return array<string, mixed> */
    private function buildChanges(array $input): array
    {
        $present = array_intersect_key($input, array_flip(self::FIELDS));

        if ($present === []) {
            return [];
        }

        return Validator::make($present, [
            'local_obra' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'observacoes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'expected_return_at' => ['sometimes', 'nullable', 'date'],
            'valor_faturamento' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'valor_frete_entrega' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'valor_frete_recolhida' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'ficha_descricao' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'horimetro_saida' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'horimetro_retorno' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'entrega_modalidade' => ['sometimes', 'string', 'in:'.implode(',', array_column(LogisticsDeliveryMode::cases(), 'value'))],
            'entrega_agendada_em' => ['sometimes', 'nullable', 'date'],
            'entrega_turno' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_column(LogisticsShift::cases(), 'value'))],
            'entrega_observacoes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'retirada_modalidade' => ['sometimes', 'string', 'in:'.implode(',', array_column(LogisticsReturnMode::cases(), 'value'))],
            'retirada_agendada_em' => ['sometimes', 'nullable', 'date'],
            'retirada_turno' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_column(LogisticsShift::cases(), 'value'))],
            'retirada_observacoes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'contrato_clausula_prorata' => ['sometimes', 'boolean'],
        ])->validate();
    }
}
