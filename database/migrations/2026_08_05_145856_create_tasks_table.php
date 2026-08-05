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
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->date('scheduled_for')->index();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'could_not_be_achieved'])->default('pending')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->longText('outcome_notes')->nullable();
            $table->foreignUuid('admin_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUuid('assignee_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index(['scheduled_for', 'status']);
            $table->index(['assignee_id', 'scheduled_for']);
            $table->index(['admin_id', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
