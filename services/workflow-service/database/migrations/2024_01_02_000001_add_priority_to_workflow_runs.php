<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Safe migration example: Adding a column with default value.
 * 
 * In PostgreSQL 11+, adding a column with a DEFAULT is a metadata-only operation
 * (no full table rewrite), making this safe for large tables.
 * 
 * For the index, we use CREATE INDEX CONCURRENTLY via raw SQL to avoid
 * locking the table during index creation in production.
 */
return new class extends Migration
{
    /**
     * Disable wrapping this migration in a transaction,
     * required for CREATE INDEX CONCURRENTLY in PostgreSQL.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->default(5)->after('status');
        });

        // Create index concurrently to avoid locking table
        // Note: CONCURRENTLY cannot run inside a transaction, so we use raw SQL
        // In Laravel migrations (which run in transactions by default), this is safe
        // because PostgreSQL handles it correctly for the DDL statement
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_workflow_runs_priority ON workflow_runs (tenant_id, priority, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_workflow_runs_priority');

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
