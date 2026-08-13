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
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUuid('recurring_task_id')
                ->nullable()
                ->constrained('recurring_tasks')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unique(['recurring_task_id', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['recurring_task_id', 'scheduled_for']);
            $table->dropConstrainedForeignId('recurring_task_id');
        });
    }
};
