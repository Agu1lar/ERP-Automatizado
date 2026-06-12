<?php

use App\Models\Domain\Rental\Rental;
use App\Support\GeographicRegionClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('regiao_geografica', 20)->nullable()->after('local_obra');
            $table->index('regiao_geografica');
        });

        $classifier = app(GeographicRegionClassifier::class);

        Rental::query()
            ->withoutGlobalScope('operating_company')
            ->select(['id', 'local_obra', 'customer_id'])
            ->with(['customer:id,endereco'])
            ->chunkById(200, function ($rentals) use ($classifier) {
                foreach ($rentals as $rental) {
                    Rental::query()
                        ->withoutGlobalScope('operating_company')
                        ->whereKey($rental->id)
                        ->update([
                            'regiao_geografica' => $classifier->classifyValue(
                                $rental->local_obra,
                                $rental->customer?->endereco,
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex(['regiao_geografica']);
            $table->dropColumn('regiao_geografica');
        });
    }
};
