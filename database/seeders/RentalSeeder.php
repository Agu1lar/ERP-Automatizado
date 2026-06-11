<?php

namespace Database\Seeders;

use App\Enums\RentalStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RentalSeeder extends Seeder
{
    public function run(): void
    {
        $acesso = OperatingCompany::query()->where('slug', 'acesso')->first();
        $super = OperatingCompany::query()->where('slug', 'supermaquinas')->first();

        $cust1 = Customer::query()->where('cpf_cnpj', '12345678909')->first();
        $cust2 = Customer::query()->where('cpf_cnpj', '11222333000181')->first();

        $assetA = Asset::withoutGlobalScope('operating_company')->where('codigo_patrimonio', 'AC-1001')->first();
        $assetS = Asset::withoutGlobalScope('operating_company')->where('codigo_patrimonio', 'SM-2001')->first();

        if ($assetA && $cust1) {
            $rental = Rental::create([
                'operating_company_id' => $acesso->id,
                'codigo' => 'LOC-AC-'.Str::upper(Str::random(6)),
                'asset_id' => $assetA->id,
                'customer_id' => $cust1->id,
                'status' => RentalStatus::Locado->value,
                'reserved_at' => Carbon::now()->subDays(3),
                'checkout_at' => Carbon::now()->subDays(2),
                'expected_return_at' => Carbon::now()->addDays(5)->toDateString(),
                'valor_faturamento' => 1500.00,
            ]);

            RentalItem::create([
                'rental_id' => $rental->id,
                'asset_id' => $assetA->id,
                'descricao' => 'Escavadeira 320D',
                'quantidade' => 1,
                'valor_locacao' => 1500.00,
                'ativo' => true,
            ]);
        }

        if ($assetS && $cust2) {
            $rental2 = Rental::create([
                'operating_company_id' => $super->id,
                'codigo' => 'LOC-SM-'.Str::upper(Str::random(6)),
                'asset_id' => $assetS->id,
                'customer_id' => $cust2->id,
                'status' => RentalStatus::Reservado->value,
                'reserved_at' => Carbon::now()->subDay(),
                'expected_return_at' => Carbon::now()->addDays(10)->toDateString(),
                'valor_faturamento' => 800.00,
            ]);

            RentalItem::create([
                'rental_id' => $rental2->id,
                'asset_id' => $assetS->id,
                'descricao' => 'Mini Escavadeira Vio45',
                'quantidade' => 1,
                'valor_locacao' => 800.00,
                'ativo' => true,
            ]);
        }
    }
}
