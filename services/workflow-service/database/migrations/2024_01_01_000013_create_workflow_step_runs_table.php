<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('step_id'); // references step.id from DAG definition
            $table->string('step_name');
            $table->string('step_type');
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'skipped'])
                ->default('pending');
            $table->unsignedInteger('attempt')->default(1);
            $table->jsonb('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('run_id')
                ->references('id')
                ->on('workflow_runs')
                ->onDelete('cascade');

            $table->index(['run_id', 'step_id']);
            $table->index(['run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_runs');
    }
};
