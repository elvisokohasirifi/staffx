<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $table = config('activitylog.table_name', 'activity_log');

        DB::statement("ALTER TABLE `{$table}` MODIFY `subject_id` CHAR(36) NULL");
        DB::statement("ALTER TABLE `{$table}` MODIFY `causer_id` CHAR(36) NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $table = config('activitylog.table_name', 'activity_log');

        DB::statement("ALTER TABLE `{$table}` MODIFY `subject_id` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `{$table}` MODIFY `causer_id` BIGINT UNSIGNED NULL");
    }
};
