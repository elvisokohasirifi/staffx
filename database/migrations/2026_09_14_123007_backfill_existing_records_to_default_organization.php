<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $organizationId = DB::table('organizations')
            ->where('is_default', true)
            ->value('id');

        if (! is_string($organizationId) || $organizationId === '') {
            $organizationId = (string) Str::uuid();
            $ownerId = DB::table('users')
                ->where('role', 'admin')
                ->orderBy('created_at')
                ->value('id');

            DB::table('organizations')->insert([
                'id' => $organizationId,
                'name' => config('app.name').' Default Organization',
                'is_default' => true,
                'owner_id' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['users', 'tasks', 'task_remarks', 'recurring_tasks', 'email_notifications', 'activity_log'] as $tableName) {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $organizationId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing data is intentionally retained in the Default Organization.
    }
};
