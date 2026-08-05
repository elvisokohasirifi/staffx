<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connectionName = config('activitylog.database_connection') ?: config('database.default');
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        $table = config('activitylog.table_name', 'activity_log');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::connection($connectionName)->hasTable($table)) {
            return;
        }

        foreach (['subject_id', 'causer_id'] as $column) {
            if (! Schema::connection($connectionName)->hasColumn($table, $column)) {
                continue;
            }

            $columnDefinition = $connection->selectOne(
                'SELECT COLUMN_TYPE AS column_type
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$connection->getDatabaseName(), $table, $column],
            );

            $columnType = strtolower((string) ($columnDefinition->column_type ?? ''));

            if (in_array($columnType, ['char(36)', 'varchar(36)'], true)) {
                continue;
            }

            $connection->statement("ALTER TABLE `{$table}` MODIFY `{$column}` CHAR(36) NULL");
        }
    }

    public function down(): void
    {
        $connectionName = config('activitylog.database_connection') ?: config('database.default');
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        $table = config('activitylog.table_name', 'activity_log');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::connection($connectionName)->hasTable($table)) {
            return;
        }

        foreach (['subject_id', 'causer_id'] as $column) {
            if (! Schema::connection($connectionName)->hasColumn($table, $column)) {
                continue;
            }

            $connection->statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED NULL");
        }
    }
};
