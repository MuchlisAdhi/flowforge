<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Append-only execution logs table.
     * 
     * Strategy: Use PostgreSQL with partitioning by month for high-volume logs.
     * For MVP, we use a simple indexed table. In production, this would be
     * partitioned using pg_partman or migrated to ClickHouse/TimescaleDB.
     * 
     * Justification for keeping in PostgreSQL (MVP):
     * - Single operational dependency
     * - ACID compliance for consistency
     * - Easy JOIN with workflow_runs for reporting
     * - Sufficient for <100k logs/day per tenant
     * 
     * Production migration path:
     * 1. Add table partitioning by created_at (monthly)
     * 2. Implement retention policy (archive after 90 days)
     * 3. Consider TimescaleDB extension or ClickHouse for analytics at scale
     */
    public function up(): void
    {
        Schema::create('workflow_execution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('step_id')->nullable();
            $table->enum('level', ['info', 'warning', 'error', 'debug'])->default('info');
            $table->text('message');
            $table->jsonb('context')->nullable(); // Additional structured data
            $table->timestamp('created_at');

            $table->foreign('run_id')
                ->references('id')
                ->on('workflow_runs')
                ->onDelete('cascade');

            // Key indexes for log retrieval
            $table->index(['run_id', 'created_at']);
            $table->index(['run_id', 'step_id', 'created_at']);
            $table->index('created_at'); // For partition pruning / retention
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_logs');
    }
};
