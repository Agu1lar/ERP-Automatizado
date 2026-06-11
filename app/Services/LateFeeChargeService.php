<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\LateFeeRuleScope;
use App\Models\Domain\Finance\LateFeeRule;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LateFeeChargeService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * @return array{
     *     valor_limpo: float,
     *     multa_percent: float,
     *     juros_mensal_percent: float,
     *     multa_valor: float,
     *     juros_valor: float,
     *     valor_total: float,
     *     dias_atraso: int,
     *     rule_source: string,
     *     is_applied: bool
     * }
     */
    public function breakdownForTitle(ReceivableTitle $title, ?CarbonInterface $referenceDate = null): array
    {
        if ($title->encargos_aplicados_em !== null) {
            return [
                'valor_limpo' => (float) $title->valor,
                'multa_percent' => (float) $title->multa_percent_aplicada,
                'juros_mensal_percent' => (float) $title->juros_mensal_percent_aplicada,
                'multa_valor' => (float) $title->multa_valor,
                'juros_valor' => (float) $title->juros_valor,
                'valor_total' => (float) $title->valor_total_com_encargos,
                'dias_atraso' => $title->daysOverdue(),
                'rule_source' => 'Encargos aplicados',
                'is_applied' => true,
            ];
        }

        $rule = $this->resolveRule($title);

        if (! $rule) {
            return $this->emptyBreakdown($title);
        }

        return $this->calculate(
            $title,
            (float) $rule->multa_percent,
            (float) $rule->juros_mensal_percent,
            $rule->targetLabel(),
            $referenceDate,
        );
    }

    /**
     * @return array{
     *     valor_limpo: float,
     *     multa_percent: float,
     *     juros_mensal_percent: float,
     *     multa_valor: float,
     *     juros_valor: float,
     *     valor_total: float,
     *     dias_atraso: int,
     *     rule_source: string,
     *     is_applied: bool
     * }
     */
    public function calculate(
        ReceivableTitle $title,
        float $multaPercent,
        float $jurosMensalPercent,
        string $ruleSource = 'Manual',
        ?CarbonInterface $referenceDate = null,
    ): array {
        $referenceDate ??= now()->startOfDay();
        $valor = (float) $title->valor;
        $dias = $title->vencimento->startOfDay()->lt($referenceDate)
            ? (int) $title->vencimento->diffInDays($referenceDate)
            : 0;

        $multa = round($valor * ($multaPercent / 100), 2);
        $juros = round($valor * ($jurosMensalPercent / 100) * ($dias / 30), 2);

        return [
            'valor_limpo' => $valor,
            'multa_percent' => $multaPercent,
            'juros_mensal_percent' => $jurosMensalPercent,
            'multa_valor' => $multa,
            'juros_valor' => $juros,
            'valor_total' => round($valor + $multa + $juros, 2),
            'dias_atraso' => $dias,
            'rule_source' => $ruleSource,
            'is_applied' => false,
        ];
    }

    public function resolveRule(ReceivableTitle $title): ?LateFeeRule
    {
        if ($title->rental_id) {
            $rentalRule = LateFeeRule::query()->active()->forRental($title->rental_id)->first();
            if ($rentalRule) {
                return $rentalRule;
            }
        }

        $customerRule = LateFeeRule::query()->active()->forCustomer($title->customer_id)->first();
        if ($customerRule) {
            return $customerRule;
        }

        return LateFeeRule::query()->active()->global()->first();
    }

    public function saveRule(
        LateFeeRuleScope $scope,
        float $multaPercent,
        float $jurosMensalPercent,
        ?int $customerId = null,
        ?int $rentalId = null,
        ?string $nome = null,
        ?User $user = null,
    ): LateFeeRule {
        $user ??= auth()->user();

        $attributes = match ($scope) {
            LateFeeRuleScope::Global => ['escopo' => $scope->value, 'customer_id' => null, 'rental_id' => null],
            LateFeeRuleScope::Customer => ['escopo' => $scope->value, 'customer_id' => $customerId, 'rental_id' => null],
            LateFeeRuleScope::Rental => ['escopo' => $scope->value, 'customer_id' => null, 'rental_id' => $rentalId],
        };

        $rule = LateFeeRule::updateOrCreate(
            $attributes,
            [
                'nome' => $nome,
                'multa_percent' => $multaPercent,
                'juros_mensal_percent' => $jurosMensalPercent,
                'ativo' => true,
            ],
        );

        $this->auditService->log(
            AuditAction::Updated,
            'LateFeeRule',
            $rule->id,
            null,
            $rule->toArray(),
            $user,
        );

        return $rule->fresh(['customer', 'rental']);
    }

    public function applyToTitle(
        ReceivableTitle $title,
        float $multaPercent,
        float $jurosMensalPercent,
        ?User $user = null,
    ): ReceivableTitle {
        $user ??= auth()->user();
        $breakdown = $this->calculate($title, $multaPercent, $jurosMensalPercent, 'Aplicação manual');

        $title->update([
            'multa_percent_aplicada' => $breakdown['multa_percent'],
            'juros_mensal_percent_aplicada' => $breakdown['juros_mensal_percent'],
            'multa_valor' => $breakdown['multa_valor'],
            'juros_valor' => $breakdown['juros_valor'],
            'valor_total_com_encargos' => $breakdown['valor_total'],
            'encargos_aplicados_em' => now(),
            'encargos_aplicados_por' => $user?->id,
        ]);

        return $title->fresh();
    }

    /**
     * @return Collection<int, ReceivableTitle>
     */
    public function applyBatch(
        ?CarbonInterface $vencimentoFrom,
        ?CarbonInterface $vencimentoTo,
        ?float $multaPercent = null,
        ?float $jurosMensalPercent = null,
        bool $useResolvedRulesWhenNoOverride = true,
        ?User $user = null,
    ): Collection {
        $user ??= auth()->user();

        $query = ReceivableTitle::query()
            ->overdue()
            ->with(['customer', 'rental']);

        if ($vencimentoFrom) {
            $query->whereDate('vencimento', '>=', $vencimentoFrom->toDateString());
        }

        if ($vencimentoTo) {
            $query->whereDate('vencimento', '<=', $vencimentoTo->toDateString());
        }

        return DB::transaction(function () use ($query, $multaPercent, $jurosMensalPercent, $useResolvedRulesWhenNoOverride, $user) {
            $applied = collect();

            foreach ($query->get() as $title) {
                if ($multaPercent !== null && $jurosMensalPercent !== null) {
                    $applied->push($this->applyToTitle($title, $multaPercent, $jurosMensalPercent, $user));

                    continue;
                }

                if (! $useResolvedRulesWhenNoOverride) {
                    continue;
                }

                $rule = $this->resolveRule($title);

                if (! $rule) {
                    continue;
                }

                $applied->push($this->applyToTitle(
                    $title,
                    (float) $rule->multa_percent,
                    (float) $rule->juros_mensal_percent,
                    $user,
                ));
            }

            return $applied;
        });
    }

    /** @return array{valor_limpo: float, multa_valor: float, juros_valor: float, valor_total: float, multa_percent: float, juros_mensal_percent: float, dias_atraso: int, rule_source: string, is_applied: bool} */
    private function emptyBreakdown(ReceivableTitle $title): array
    {
        $valor = (float) $title->valor;

        return [
            'valor_limpo' => $valor,
            'multa_percent' => 0.0,
            'juros_mensal_percent' => 0.0,
            'multa_valor' => 0.0,
            'juros_valor' => 0.0,
            'valor_total' => $valor,
            'dias_atraso' => $title->daysOverdue(),
            'rule_source' => 'Sem regra',
            'is_applied' => false,
        ];
    }
}
