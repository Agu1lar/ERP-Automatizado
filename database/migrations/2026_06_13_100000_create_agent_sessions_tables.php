<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operating_company_id')->nullable()->constrained('operating_companies')->nullOnDelete();
            $table->string('channel', 20)->default('web');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_activity_at']);
        });

        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['agent_session_id', 'created_at']);
        });

        Schema::create('agent_command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operating_company_id')->nullable()->constrained('operating_companies')->nullOnDelete();
            $table->string('command');
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->boolean('dry_run')->default(false);
            $table->boolean('ok')->default(false);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'command']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_command_logs');
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('agent_sessions');
    }
};
