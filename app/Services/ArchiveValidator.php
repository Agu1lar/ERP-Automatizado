<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\DeliveryManifestStatus;
use App\Enums\RentalStatus;
use App\Exceptions\ArchiveBlockedException;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Logistics\DeliveryVehicle;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ArchiveValidator
{
    public function validate(Model $model): void
    {
        match ($model::class) {
            Company::class => $this->validateCompany($model),
            Person::class => null,
            Customer::class => $this->validateCustomer($model),
            User::class => $this->validateUser($model),
            EquipmentCategory::class => $this->validateCategory($model),
            EquipmentModel::class => $this->validateEquipmentModel($model),
            Asset::class => $this->validateAsset($model),
            Yard::class => $this->validateYard($model),
            DeliveryDriver::class => $this->validateDriver($model),
            DeliveryVehicle::class => $this->validateVehicle($model),
            PartCatalogItem::class => $this->validatePart($model),
            default => null,
        };
    }

    private function validateCompany(Company $company): void
    {
        $peopleCount = $company->people()->count();

        if ($peopleCount > 0) {
            throw new ArchiveBlockedException(
                "Não é possível arquivar: existem {$peopleCount} pessoa(s) vinculada(s). Desvincule ou arquive-as antes."
            );
        }
    }

    private function validateCustomer(Customer $customer): void
    {
        $activeRentals = $customer->rentals()
            ->whereNotIn('status', [
                RentalStatus::Concluido->value,
                RentalStatus::Cancelado->value,
            ])
            ->count();

        if ($activeRentals > 0) {
            throw new ArchiveBlockedException(
                "Não é possível arquivar: o cliente possui {$activeRentals} locação(ões) ativa(s). Encerre ou cancele antes."
            );
        }
    }

    private function validateUser(User $user): void
    {
        if (auth()->id() === $user->id) {
            throw new ArchiveBlockedException('Não é possível arquivar o seu próprio usuário.');
        }
    }

    private function validateCategory(EquipmentCategory $category): void
    {
        $modelsCount = $category->models()->count();

        if ($modelsCount > 0) {
            throw new ArchiveBlockedException(
                "Não é possível arquivar: a categoria possui {$modelsCount} modelo(s). Arquive os modelos antes."
            );
        }
    }

    private function validateEquipmentModel(EquipmentModel $model): void
    {
        $assetsCount = $model->assets()->count();

        if ($assetsCount > 0) {
            throw new ArchiveBlockedException(
                "Não é possível arquivar: o modelo possui {$assetsCount} patrimônio(s). Arquive ou transfira antes."
            );
        }
    }

    private function validateAsset(Asset $asset): void
    {
        $status = $asset->statusEnum();

        $safeToArchive = in_array($status, [
            AssetStatus::Disponivel,
            AssetStatus::Bloqueado,
            AssetStatus::Sucata,
            AssetStatus::Cancelado,
            AssetStatus::Arquivado,
        ], true);

        if (! $safeToArchive) {
            throw new ArchiveBlockedException(
                'Não é possível arquivar: patrimônio está com status "'.$status->label().'". Conclua locação, manutenção ou inspeção antes.'
            );
        }
    }

    private function validateYard(Yard $yard): void
    {
        $assetsCount = $yard->assets()->count();

        if ($assetsCount > 0) {
            throw new ArchiveBlockedException(
                "Não é possível arquivar: o pátio possui {$assetsCount} patrimônio(s) vinculado(s). Transfira-os antes."
            );
        }
    }

    private function validateDriver(DeliveryDriver $driver): void
    {
        $openManifests = $driver->manifests()
            ->whereIn('status', [
                DeliveryManifestStatus::Rascunho->value,
                DeliveryManifestStatus::EmRota->value,
            ])
            ->count();

        if ($openManifests > 0) {
            throw new ArchiveBlockedException(
                "Não é possível arquivar: o motorista está em {$openManifests} romaneio(s) aberto(s)."
            );
        }
    }

    private function validateVehicle(DeliveryVehicle $vehicle): void
    {
        $openManifests = $vehicle->manifests()
            ->whereIn('status', [
                DeliveryManifestStatus::Rascunho->value,
                DeliveryManifestStatus::EmRota->value,
            ])
            ->count();

        if ($openManifests > 0) {
            throw new ArchiveBlockedException(
                "Não é possível arquivar: o veículo está em {$openManifests} romaneio(s) aberto(s)."
            );
        }
    }

    private function validatePart(PartCatalogItem $part): void
    {
        if ((float) $part->estoque_atual > 0) {
            throw new ArchiveBlockedException(
                'Não é possível arquivar: a peça ainda possui estoque ('.number_format((float) $part->estoque_atual, 2, ',', '.').'). Zere o saldo antes.'
            );
        }
    }
}
