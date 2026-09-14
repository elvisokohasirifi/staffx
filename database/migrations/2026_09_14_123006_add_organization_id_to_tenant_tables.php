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
        if (! Schema::hasForeignKey('organizations', ['owner_id'])) {
            Schema::table('organizations', function (Blueprint $table): void {
                $table->foreign('owner_id', 'organizations_owner_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        foreach (['users', 'tasks', 'task_remarks', 'recurring_tasks', 'email_notifications', 'activity_log'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'organization_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->uuid('organization_id')->nullable();
                });
            }

            if (! Schema::hasIndex($tableName, ['organization_id'])) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->index('organization_id', "{$tableName}_organization_id_index");
                });
            }

            if (! Schema::hasForeignKey($tableName, ['organization_id'])) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->foreign('organization_id', "{$tableName}_organization_id_foreign")
                        ->references('id')
                        ->on('organizations')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                });
            }
        }

        if (! Schema::hasIndex('tasks', 'tasks_organization_scheduled_status_index')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->index(['organization_id', 'scheduled_for', 'status'], 'tasks_organization_scheduled_status_index');
            });
        }

        if (! Schema::hasIndex('tasks', 'tasks_organization_assignee_scheduled_index')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->index(['organization_id', 'assignee_id', 'scheduled_for'], 'tasks_organization_assignee_scheduled_index');
            });
        }

        if (! Schema::hasIndex('recurring_tasks', 'recurring_tasks_organization_assignee_active_index')) {
            Schema::table('recurring_tasks', function (Blueprint $table): void {
                $table->index(['organization_id', 'assignee_id', 'is_active'], 'recurring_tasks_organization_assignee_active_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_organization_scheduled_status_index');
            $table->dropIndex('tasks_organization_assignee_scheduled_index');
        });

        Schema::table('recurring_tasks', function (Blueprint $table): void {
            $table->dropIndex('recurring_tasks_organization_assignee_active_index');
        });

        foreach (['users', 'tasks', 'task_remarks', 'recurring_tasks', 'email_notifications', 'activity_log'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign("{$tableName}_organization_id_foreign");
                $table->dropIndex("{$tableName}_organization_id_index");
                $table->dropColumn('organization_id');
            });
        }

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropForeign('organizations_owner_id_foreign');
        });
    }
};
