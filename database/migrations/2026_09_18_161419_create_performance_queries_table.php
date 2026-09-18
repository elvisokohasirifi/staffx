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
        Schema::create('performance_queries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->cascadeOnUpdate()->nullOnDelete();
            $table->string('question', 500);
            $table->string('chart_type', 20)->default('bar');
            $table->json('result_data');
            $table->timestamps();

            $table->index(['organization_id', 'admin_id', 'created_at'], 'performance_queries_history_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_queries');
    }
};
