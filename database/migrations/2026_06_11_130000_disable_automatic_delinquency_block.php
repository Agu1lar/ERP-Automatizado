<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')->update(['bloqueio_inadimplencia' => false]);
    }

    public function down(): void
    {
        // Campo legado — bloqueio por inadimplência não é mais aplicado pelo sistema.
    }
};
