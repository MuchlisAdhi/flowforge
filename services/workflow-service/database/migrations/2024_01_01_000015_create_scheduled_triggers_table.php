<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('workflow_id');
            $table->string('cron_expression'); // e.g., "0 8 * * *"
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('next_trigger_at')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('workflows')
                ->onDelete('cascade');

            $table->index(['tenant_id', 'is_active']);
            $table->index('next_trigger_at');
        });

        Schema::create('webhook_triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('workflow_id');
            $table->string('secret_token', 64)->unique(); // Webhook verification
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('workflows')
                ->onDelete('cascade');

            $table->index(['tenant_id', 'is_active']);
            $table->index('secret_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_triggers');
        Schema::dropIfExists('scheduled_triggers');
    }
};
