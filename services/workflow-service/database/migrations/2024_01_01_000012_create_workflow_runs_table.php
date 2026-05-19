<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('workflow_id');
            $table->uuid('workflow_version_id');
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'cancelled', 'timeout'])
                ->default('pending');
            $table->enum('trigger_type', ['manual', 'scheduled', 'webhook'])->default('manual');
            $table->uuid('triggered_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('workflows')
                ->onDelete('cascade');

            $table->foreign('workflow_version_id')
                ->references('id')
                ->on('workflow_versions')
                ->onDelete('cascade');

            // Key indexes for dashboard queries
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'workflow_id', 'created_at']);
            $table->index(['workflow_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
