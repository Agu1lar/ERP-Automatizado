<?php

namespace App\Support;

use App\Enums\LogisticsDeliveryMode;
use App\Enums\LogisticsReturnMode;
use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LogisticsDailyQuery
{
    /** @return Collection<int, Rental> */
    public function scheduledDeliveries(CarbonInterface $date): Collection
    {
        return $this->baseQuery()
            ->whereDate('entrega_agendada_em', $date->toDateString())
            ->whereIn('status', [
                RentalStatus::Reservado->value,
                RentalStatus::Locado->value,
            ])
            ->where(function ($query) {
                $query->whereNull('entrega_modalidade')
                    ->orWhere('entrega_modalidade', LogisticsDeliveryMode::EmpresaEntrega->value);
            })
            ->orderByRaw("CASE entrega_turno WHEN 'manha' THEN 1 WHEN 'tarde' THEN 2 ELSE 3 END")
            ->orderBy('entrega_agendada_em')
            ->get();
    }

    /** Cliente vem buscar no pátio na data agendada. @return Collection<int, Rental> */
    public function customerPickupsAtYard(CarbonInterface $date): Collection
    {
        return $this->baseQuery()
            ->whereDate('entrega_agendada_em', $date->toDateString())
            ->whereIn('status', [
                RentalStatus::Reservado->value,
                RentalStatus::Locado->value,
            ])
            ->where('entrega_modalidade', LogisticsDeliveryMode::ClienteRetira->value)
            ->orderByRaw("CASE entrega_turno WHEN 'manha' THEN 1 WHEN 'tarde' THEN 2 ELSE 3 END")
            ->orderBy('entrega_agendada_em')
            ->get();
    }

    /** @return Collection<int, Rental> */
    public function scheduledPickups(CarbonInterface $date): Collection
    {
        return $this->baseQuery()
            ->whereDate('retirada_agendada_em', $date->toDateString())
            ->where('status', RentalStatus::Locado->value)
            ->where(function ($query) {
                $query->whereNull('retirada_modalidade')
                    ->orWhere('retirada_modalidade', LogisticsReturnMode::EmpresaRecolhe->value);
            })
            ->orderByRaw("CASE retirada_turno WHEN 'manha' THEN 1 WHEN 'tarde' THEN 2 ELSE 3 END")
            ->orderBy('retirada_agendada_em')
            ->get();
    }

    /** Cliente devolve equipamento no pátio na data agendada. @return Collection<int, Rental> */
    public function customerReturnsAtYard(CarbonInterface $date): Collection
    {
        return $this->baseQuery()
            ->where('status', RentalStatus::Locado->value)
            ->where('retirada_modalidade', LogisticsReturnMode::ClienteDevolve->value)
            ->where(function ($query) use ($date) {
                $query->whereDate('retirada_agendada_em', $date->toDateString())
                    ->orWhere(function ($inner) use ($date) {
                        $inner->whereNull('retirada_agendada_em')
                            ->whereDate('expected_return_at', $date->toDateString());
                    });
            })
            ->orderByRaw('COALESCE(retirada_agendada_em, expected_return_at)')
            ->get();
    }

    /** Retornos previstos no dia sem retirada agendada — só quando a empresa recolhe. @return Collection<int, Rental> */
    public function expectedReturnsWithoutPickupSchedule(CarbonInterface $date): Collection
    {
        return $this->baseQuery()
            ->where('status', RentalStatus::Locado->value)
            ->whereDate('expected_return_at', $date->toDateString())
            ->whereNull('retirada_agendada_em')
            ->where(function ($query) {
                $query->whereNull('retirada_modalidade')
                    ->orWhere('retirada_modalidade', LogisticsReturnMode::EmpresaRecolhe->value);
            })
            ->orderBy('expected_return_at')
            ->get();
    }

    /** @return array{entregas: int, cliente_retira: int, retiradas: int, cliente_devolve: int, retornos_previstos: int} */
    public function countsForDate(CarbonInterface $date): array
    {
        return [
            'entregas' => $this->scheduledDeliveries($date)->count(),
            'cliente_retira' => $this->customerPickupsAtYard($date)->count(),
            'retiradas' => $this->scheduledPickups($date)->count(),
            'cliente_devolve' => $this->customerReturnsAtYard($date)->count(),
            'retornos_previstos' => $this->expectedReturnsWithoutPickupSchedule($date)->count(),
        ];
    }

    private function baseQuery()
    {
        return Rental::query()
            ->with(['customer', 'asset.yard', 'asset.equipmentModel.category']);
    }
}
