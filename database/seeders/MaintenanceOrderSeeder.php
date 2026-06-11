<?php

namespace Database\Seeders;

use App\Enums\MaintenanceOrderType;
use App\Enums\MaintenanceOrderStatus;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Customer\Customer;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MaintenanceOrderSeeder extends Seeder
{
    public function run(): void
    {
        $acesso = OperatingCompany::query()->where('slug', 'acesso')->first();
        $asset = Asset::withoutGlobalScope('operating_company')->where('codigo_patrimonio', 'AC-1002')->first();
        $customer = Customer::query()->where('cpf_cnpj', '12345678909')->first();

        if (! $asset || ! $acesso) {
            return;
        }

        MaintenanceOrder::create([
            'operating_company_id' => $acesso->id,
            'codigo' => 'OS-'.strtoupper(substr(md5((string) time()), 0, 6)),
            'asset_id' => $asset->id,
            'rental_id' => null,
            'customer_id' => $customer?->id,
            'status' => MaintenanceOrderStatus::Aberta->value,
            'tipo' => MaintenanceOrderType::Corretiva->value,
            'prioridade' => 'normal',
            'impeditiva' => false,
            'descricao_problema' => 'Vazamento hidráulico identificado no braço.',
            'opened_at' => Carbon::now()->subDays(1),
            'opened_by' => null,
        ]);
    }
}
