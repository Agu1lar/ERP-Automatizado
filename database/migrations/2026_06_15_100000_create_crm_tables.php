<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dateTime('ultimo_contato_em')->nullable()->after('bloqueado_by');
            $table->date('proximo_follow_up_em')->nullable()->after('ultimo_contato_em');
            $table->foreignId('follow_up_assigned_to')->nullable()->after('proximo_follow_up_em')->constrained('users')->nullOnDelete();
        });

        Schema::create('commercial_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->nullable()->constrained('operating_companies')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('rental_quote_id')->nullable()->constrained('rental_quotes')->nullOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained('rentals')->nullOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('stage', 30)->default('lead');
            $table->decimal('valor_estimado', 12, 2)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('proximo_follow_up_em')->nullable();
            $table->dateTime('ultimo_contato_em')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['stage', 'proximo_follow_up_em']);
            $table->index(['customer_id', 'stage']);
        });

        Schema::create('commercial_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('commercial_opportunity_id')->nullable()->constrained('commercial_opportunities')->nullOnDelete();
            $table->string('tipo', 30);
            $table->text('descricao');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('proximo_follow_up_em')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->nullable()->constrained('operating_companies')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('template', 50)->nullable();
            $table->string('recipient', 30);
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->text('provider_response')->nullable();
            $table->string('campaign_ref')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('commercial_activities');
        Schema::dropIfExists('commercial_opportunities');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('follow_up_assigned_to');
            $table->dropColumn(['ultimo_contato_em', 'proximo_follow_up_em']);
        });
    }
};
