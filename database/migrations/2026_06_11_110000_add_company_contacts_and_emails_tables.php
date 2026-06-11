<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('cargo')->nullable();
            $table->string('telefone', 30)->nullable();
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'principal']);
        });

        Schema::create('company_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('rotulo')->nullable();
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'principal']);
        });

        foreach (DB::table('companies')->get() as $company) {
            if (filled($company->contato_principal) || filled($company->telefone)) {
                DB::table('company_contacts')->insert([
                    'company_id' => $company->id,
                    'nome' => $company->contato_principal ?: 'Contato',
                    'telefone' => $company->telefone,
                    'principal' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (filled($company->email)) {
                DB::table('company_emails')->insert([
                    'company_id' => $company->id,
                    'email' => $company->email,
                    'rotulo' => 'Principal',
                    'principal' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['telefone', 'email', 'contato_principal']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('telefone', 30)->nullable()->after('tipo');
            $table->string('email')->nullable()->after('telefone');
            $table->string('contato_principal')->nullable()->after('endereco');
        });

        foreach (DB::table('companies')->get() as $company) {
            $contact = DB::table('company_contacts')
                ->where('company_id', $company->id)
                ->orderByDesc('principal')
                ->first();

            $email = DB::table('company_emails')
                ->where('company_id', $company->id)
                ->orderByDesc('principal')
                ->first();

            DB::table('companies')->where('id', $company->id)->update([
                'contato_principal' => $contact?->nome,
                'telefone' => $contact?->telefone,
                'email' => $email?->email,
            ]);
        }

        Schema::dropIfExists('company_emails');
        Schema::dropIfExists('company_contacts');
    }
};
