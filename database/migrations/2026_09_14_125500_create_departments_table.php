<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('department_administrators', function (Blueprint $table) {
            $table->foreignUuid('department_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->primary(['department_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_administrators');
        Schema::dropIfExists('departments');
    }
};
