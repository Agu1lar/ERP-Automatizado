<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operating_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('queued');
            $table->string('title');
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->unsignedSmallInteger('total_steps')->default(0);
            $table->json('steps');
            $table->json('resource_snapshots')->nullable();
            $table->json('step_results')->nullable();
            $table->text('error_message')->nullable();
            $table->text('conflict_reason')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['operating_company_id', 'idempotency_key']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('agent_task_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_task_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id');
            $table->timestamp('snapshot_updated_at')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_task_resources');
        Schema::dropIfExists('agent_tasks');
    }
};
