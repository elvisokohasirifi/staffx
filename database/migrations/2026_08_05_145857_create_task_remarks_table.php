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
        Schema::create('task_remarks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUuid('parent_remark_id')->nullable()->constrained('task_remarks')->nullOnDelete();
            $table->longText('body');
            $table->boolean('is_admin_remark')->default(false);
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_remarks');
    }
};
