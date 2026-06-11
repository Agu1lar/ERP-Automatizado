<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\User;
use App\Services\RentalService;

class RentalTransferCommercialCommand extends AbstractAgentCommand implements SupportsDryRun
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly RentalService $rentalService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'rental.transfer_commercial';
    }

    public static function description(): string
    {
        return 'Transfere o responsável comercial de uma locação concluída para outro usuário.';
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
                ['commercial_user_id', 'commercial_user_email'],
            ],
            'properties' => [
                'rental_id' => ['type' => 'integer'],
                'rental_codigo' => ['type' => 'string'],
                'commercial_user_id' => ['type' => 'integer'],
                'commercial_user_email' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);

        if (! $user->can('transferCommercialUser', $rental)) {
            return $this->failure('Sem permissão para transferir responsável comercial desta locação.', 'forbidden');
        }

        $newUser = $this->resolveCommercialUser($input);
        $rental = $this->rentalService->transferCommercialUser($rental, $newUser, $user);

        return $this->success(
            "Responsável comercial de **{$rental->codigo}** alterado para **{$newUser->name}**.",
            $this->contextBuilder->rental($rental),
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $rental = $this->resolveRental($input);
        $newUser = $this->resolveCommercialUser($input);

        return AgentCommandResult::preview(
            "Simulação: transferir comercial de **{$rental->codigo}** para **{$newUser->name}**.",
            ['rental_codigo' => $rental->codigo, 'new_user' => $newUser->email],
        );
    }

    /** @param  array<string, mixed>  $input */
    private function resolveCommercialUser(array $input): User
    {
        if (! empty($input['commercial_user_id'])) {
            return User::query()->where('ativo', true)->findOrFail((int) $input['commercial_user_id']);
        }

        $email = trim((string) ($input['commercial_user_email'] ?? ''));

        if ($email === '') {
            throw new \InvalidArgumentException('Informe commercial_user_id ou commercial_user_email.');
        }

        $found = User::query()->where('ativo', true)->where('email', $email)->first();

        if (! $found) {
            throw new \InvalidArgumentException("Usuário ativo não encontrado: {$email}");
        }

        return $found;
    }
}
