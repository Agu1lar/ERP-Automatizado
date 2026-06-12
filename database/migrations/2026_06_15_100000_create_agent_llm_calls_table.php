<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_llm_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operating_company_id')->nullable()->constrained('operating_companies')->nullOnDelete();
            $table->foreignId('agent_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('call_type', 40);
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->default(0);
            $table->boolean('success')->default(false);
            $table->string('failure_reason', 60)->nullable();
            $table->boolean('used_fallback')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['operating_company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['call_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_llm_calls');
    }
};
