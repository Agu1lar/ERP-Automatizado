<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('obra_latitude', 10, 7)->nullable()->after('regiao_geografica');
            $table->decimal('obra_longitude', 10, 7)->nullable()->after('obra_latitude');
            $table->string('obra_geocode_precision', 20)->nullable()->after('obra_longitude');
            $table->timestamp('obra_geocoded_at')->nullable()->after('obra_geocode_precision');
        });

        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->id();
            $table->string('address_hash', 64)->unique();
            $table->text('query_text');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('precision', 20)->default('approximate');
            $table->string('provider', 30)->default('nominatim');
            $table->json('provider_payload')->nullable();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn([
                'obra_latitude',
                'obra_longitude',
                'obra_geocode_precision',
                'obra_geocoded_at',
            ]);
        });
    }
};
