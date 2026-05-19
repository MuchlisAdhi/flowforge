<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id');
            $table->unsignedInteger('version');
            $table->jsonb('definition'); // DAG definition including steps and execution plan
            $table->unsignedInteger('timeout_seconds')->default(300);
            $table->string('change_note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('workflows')
                ->onDelete('cascade');

            $table->unique(['workflow_id', 'version']);
            $table->index('workflow_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
