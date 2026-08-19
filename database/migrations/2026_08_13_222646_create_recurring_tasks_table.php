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
        Schema::create('recurring_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->time('scheduled_time')->default('23:59:00');
            $table->json('recurring_days');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUuid('admin_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUuid('assignee_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index(['assignee_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_tasks');
    }
};
