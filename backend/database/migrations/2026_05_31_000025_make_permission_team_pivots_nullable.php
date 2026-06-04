<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teamKey = config('permission.column_names.team_foreign_key', 'workspace_id');
        $tables = config('permission.table_names');

        foreach ([$tables['model_has_roles'], $tables['model_has_permissions']] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$teamKey}` BIGINT UNSIGNED NULL");
            } elseif ($driver === 'sqlite') {
                // SQLite tests recreate schema via migrations; column is NOT NULL in base migration.
                // Recreate is not needed when using in-memory DB with fresh migrate — handled in 000026.
            }
        }
    }

    public function down(): void
    {
        $teamKey = config('permission.column_names.team_foreign_key', 'workspace_id');
        $tables = config('permission.table_names');

        foreach ([$tables['model_has_roles'], $tables['model_has_permissions']] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$teamKey}` BIGINT UNSIGNED NOT NULL");
            }
        }
    }
};
