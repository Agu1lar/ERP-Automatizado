<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->string('field_key');
            $table->string('label');
            $table->string('field_type')->default('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('triggers_warning')->default(false);
            $table->string('warning_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entity_type', 'field_key']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_definition_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_definition_id', 'entity_type', 'entity_id'], 'cfv_unique');
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('user_hidden_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'custom_field_definition_id'], 'user_hidden_cf_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hidden_custom_fields');
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
