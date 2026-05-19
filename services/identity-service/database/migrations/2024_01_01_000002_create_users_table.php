<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->enum('role', ['admin', 'editor', 'viewer'])->default('viewer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->unique(['tenant_id', 'email']);
            $table->index('tenant_id');
            $table->index('email');
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
