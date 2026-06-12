<?php

namespace App\Services;

use App\Enums\DeliveryManifestStatus;
use App\Enums\DeliveryManifestStopStatus;
use App\Enums\DeliveryManifestStopType;
use App\Enums\LogisticsShift;
use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Logistics\DeliveryManifest;
use App\Models\Domain\Logistics\DeliveryManifestStop;
use App\Models\Domain\Logistics\DeliveryProof;
use App\Models\Domain\Logistics\DeliveryVehicle;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Support\LogisticsDailyQuery;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DeliveryManifestService
{
    public function __construct(
        private readonly LogisticsDailyQuery $dailyQuery,
    ) {}

    public function findOrGenerateForDate(CarbonInterface $date, ?User $user = null): DeliveryManifest
    {
        $user ??= auth()->user();
        $existing = DeliveryManifest::query()
            ->whereDate('data', $date->toDateString())
            ->where('status', '!=', DeliveryManifestStatus::Cancelado->value)
            ->first();

        if ($existing) {
            return $existing->load(['stops.rental.customer', 'stops.rental.asset.yard', 'driver', 'vehicle']);
        }

        return $this->generateForDate($date, $user);
    }

    public function generateForDate(CarbonInterface $date, ?User $user = null): DeliveryManifest
    {
        $user ??= auth()->user();

        if (DeliveryManifest::query()->whereDate('data', $date->toDateString())->exists()) {
            throw new InvalidArgumentException('Já existe romaneio para esta data.');
        }

        $stops = $this->buildStopsForDate($date);

        if ($stops->isEmpty()) {
            throw new InvalidArgumentException('Nenhuma entrega ou retirada pela frota nesta data para gerar romaneio.');
        }

        return DB::transaction(function () use ($date, $stops, $user) {
            $manifest = DeliveryManifest::create([
                'codigo' => $this->generateCodigo(),
                'data' => $date->toDateString(),
                'status' => DeliveryManifestStatus::Rascunho->value,
                'created_by' => $user?->id,
            ]);

            foreach ($stops as $index => $stop) {
                $manifest->stops()->create([
                    'rental_id' => $stop['rental_id'],
                    'sequencia' => $index + 1,
                    'tipo' => $stop['tipo'],
                    'status' => DeliveryManifestStopStatus::Pendente->value,
                    'endereco' => $stop['endereco'],
                    'turno' => $stop['turno'],
                    'observacoes' => $stop['observacoes'],
                ]);
            }

            return $manifest->fresh(['stops.rental.customer', 'stops.rental.asset.yard', 'driver', 'vehicle']);
        });
    }

    public function assignResources(
        DeliveryManifest $manifest,
        ?DeliveryDriver $driver,
        ?DeliveryVehicle $vehicle,
    ): DeliveryManifest {
        if ($manifest->statusEnum() === DeliveryManifestStatus::Concluido) {
            throw new InvalidArgumentException('Romaneio concluído não pode ser alterado.');
        }

        $manifest->update([
            'delivery_driver_id' => $driver?->id,
            'delivery_vehicle_id' => $vehicle?->id,
        ]);

        return $manifest->fresh(['driver', 'vehicle']);
    }

    public function startRoute(DeliveryManifest $manifest, ?User $user = null): DeliveryManifest
    {
        if ($manifest->statusEnum() !== DeliveryManifestStatus::Rascunho) {
            throw new InvalidArgumentException('Somente romaneios em rascunho podem iniciar rota.');
        }

        if (! $manifest->delivery_driver_id || ! $manifest->delivery_vehicle_id) {
            throw new InvalidArgumentException('Informe motorista e veículo antes de iniciar a rota.');
        }

        $manifest->update([
            'status' => DeliveryManifestStatus::EmRota->value,
            'started_at' => now(),
        ]);

        return $manifest->fresh();
    }

    public function recordProof(
        DeliveryManifestStop $stop,
        string $receptorNome,
        ?string $assinaturaDataUrl,
        ?UploadedFile $foto = null,
        ?string $observacoes = null,
        ?User $user = null,
    ): DeliveryProof {
        $user ??= auth()->user();
        $stop->loadMissing('manifest', 'proof');

        if ($stop->statusEnum() === DeliveryManifestStopStatus::Concluida) {
            throw new InvalidArgumentException('Parada já possui comprovante registrado.');
        }

        if (blank($receptorNome)) {
            throw new InvalidArgumentException('Nome do receptor é obrigatório.');
        }

        if (blank($assinaturaDataUrl) && $foto === null) {
            throw new InvalidArgumentException('Informe assinatura ou foto do comprovante.');
        }

        return DB::transaction(function () use ($stop, $receptorNome, $assinaturaDataUrl, $foto, $observacoes, $user) {
            $fotoPath = null;

            if ($foto) {
                $fotoPath = $foto->store('delivery-proofs/'.now()->format('Y/m'), 'public');
            }

            $proof = DeliveryProof::create([
                'delivery_manifest_stop_id' => $stop->id,
                'receptor_nome' => trim($receptorNome),
                'assinatura_imagem' => $this->normalizeSignature($assinaturaDataUrl),
                'foto_path' => $fotoPath,
                'observacoes' => $observacoes,
                'user_id' => $user?->id,
                'registrado_em' => now(),
            ]);

            $stop->update(['status' => DeliveryManifestStopStatus::Concluida->value]);
            $this->refreshManifestCompletion($stop->manifest->fresh(['stops']));

            return $proof->fresh();
        });
    }

    public function markStopNotDone(DeliveryManifestStop $stop, ?string $motivo = null): DeliveryManifestStop
    {
        $stop->loadMissing('manifest');

        $stop->update([
            'status' => DeliveryManifestStopStatus::NaoRealizada->value,
            'observacoes' => trim(($stop->observacoes ?? '').($motivo ? "\n{$motivo}" : '')) ?: null,
        ]);

        $this->refreshManifestCompletion($stop->manifest->fresh(['stops']));

        return $stop->fresh();
    }

    /** @return Collection<int, array{rental_id: int, tipo: string, endereco: ?string, turno: ?string, observacoes: ?string, sort_key: string}> */
    private function buildStopsForDate(CarbonInterface $date): Collection
    {
        $stops = collect();

        foreach ($this->dailyQuery->scheduledDeliveries($date) as $rental) {
            $stops->push($this->mapRentalStop($rental, DeliveryManifestStopType::Entrega));
        }

        foreach ($this->dailyQuery->scheduledPickups($date) as $rental) {
            $stops->push($this->mapRentalStop($rental, DeliveryManifestStopType::Retirada));
        }

        foreach ($this->dailyQuery->expectedReturnsWithoutPickupSchedule($date) as $rental) {
            $stops->push($this->mapRentalStop($rental, DeliveryManifestStopType::Retirada));
        }

        return $stops
            ->sortBy('sort_key')
            ->values();
    }

  /** @return array{rental_id: int, tipo: string, endereco: ?string, turno: ?string, observacoes: ?string, sort_key: string} */
    private function mapRentalStop(Rental $rental, DeliveryManifestStopType $tipo): array
    {
        $turno = $tipo === DeliveryManifestStopType::Entrega
            ? $rental->entrega_turno
            : ($rental->retirada_turno ?? null);

        $obs = $tipo === DeliveryManifestStopType::Entrega
            ? $rental->entrega_observacoes
            : $rental->retirada_observacoes;

        $shiftOrder = match (LogisticsShift::tryFrom((string) $turno)) {
            LogisticsShift::Manha => '1',
            LogisticsShift::Tarde => '2',
            default => '3',
        };

        return [
            'rental_id' => $rental->id,
            'tipo' => $tipo->value,
            'endereco' => $rental->local_obra,
            'turno' => $turno,
            'observacoes' => $obs,
            'sort_key' => $shiftOrder.'-'.$tipo->value.'-'.$rental->codigo,
        ];
    }

    private function refreshManifestCompletion(DeliveryManifest $manifest): void
    {
        $pending = $manifest->stops()
            ->where('status', DeliveryManifestStopStatus::Pendente->value)
            ->exists();

        if (! $pending && $manifest->statusEnum() === DeliveryManifestStatus::EmRota) {
            $manifest->update([
                'status' => DeliveryManifestStatus::Concluido->value,
                'completed_at' => now(),
            ]);
        }
    }

    private function normalizeSignature(?string $dataUrl): ?string
    {
        if (blank($dataUrl)) {
            return null;
        }

        if (! str_starts_with($dataUrl, 'data:image/')) {
            throw new InvalidArgumentException('Formato de assinatura inválido.');
        }

        return $dataUrl;
    }

    private function generateCodigo(): string
    {
        $next = (DeliveryManifest::withoutGlobalScope('operating_company')->max('id') ?? 0) + 1;

        return 'ROM-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
