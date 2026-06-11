<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalPricingPeriod;
use App\Models\User;
use App\Services\RentalService;
use App\Support\WorkflowNextStep;
use Carbon\Carbon;

class RentalReserveCommand extends AbstractAgentCommand implements SupportsDryRun
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly RentalService $rentalService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'rental.reserve';
  }

  public static function description(): string
  {
    return 'Cria uma reserva de locação para patrimônio e cliente informados.';
  }

  public function permission(): string
  {
    return 'rentals.reserve';
  }

  /** @return list<array{type: string, id: int}> */
  public function affectedResources(array $input): array
  {
    try {
      $asset = $this->resolveAsset($input);
      $customer = $this->resolveCustomer($input);

      return [
        ['type' => 'asset', 'id' => $asset->id],
        ['type' => 'customer', 'id' => $customer->id],
      ];
    } catch (\Throwable) {
      return [];
    }
  }

  protected function declaredResourceTypes(): array
  {
    return ['asset', 'customer'];
  }

  public function inputSchema(): array
  {
    return [
      'type' => 'object',
      'oneOfRequired' => [
        ['asset_id', 'asset_codigo'],
        ['customer_id', 'customer_cpf_cnpj'],
      ],
      'properties' => [
        'asset_id' => ['type' => 'integer'],
        'asset_codigo' => ['type' => 'string'],
        'customer_id' => ['type' => 'integer'],
        'customer_cpf_cnpj' => ['type' => 'string'],
        'expected_return_at' => ['type' => 'string', 'format' => 'date'],
        'local_obra' => ['type' => 'string'],
        'observacoes' => ['type' => 'string'],
        'pricing_period' => ['type' => 'string', 'enum' => ['diaria', 'semanal', 'mensal']],
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $asset = $this->resolveAsset($input);
    $customer = $this->resolveCustomer($input);

    $period = ! empty($input['pricing_period'])
      ? RentalPricingPeriod::from($input['pricing_period'])
      : null;

    $rental = $this->rentalService->reserve(
      $asset,
      $customer,
      ! empty($input['expected_return_at']) ? Carbon::parse($input['expected_return_at']) : null,
      $input['observacoes'] ?? null,
      $user,
      $input['local_obra'] ?? null,
      $period,
    );

    return $this->success(
      "Reserva {$rental->codigo} criada.",
      $this->contextBuilder->rental($rental->fresh()),
      [
        [
          'label' => 'Registrar saída',
          'command' => 'rental.checkout',
          'params' => ['rental_id' => $rental->id],
          'primary' => true,
        ],
        ...WorkflowNextStep::rentalAfterReserve($rental),
      ],
    );
  }

  public function dryRun(array $input, User $user): AgentCommandResult
  {
    $assetCode = $input['asset_codigo'] ?? $input['asset_id'] ?? '?';
    $customer = $input['customer_cpf_cnpj'] ?? $input['customer_id'] ?? '?';

    return AgentCommandResult::preview(
      "Simulação: reservar patrimônio **{$assetCode}** para cliente **{$customer}**.",
      ['entity' => 'rental', 'dry_run' => true],
    );
  }
}
