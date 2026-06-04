<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'workspace_id');
        $tables = config('permission.table_names');

        foreach ([$tables['model_has_roles'], $tables['model_has_permissions']] as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$teamKey}` BIGINT UNSIGNED NULL");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'workspace_id');
        $tables = config('permission.table_names');

        foreach ([$tables['model_has_roles'], $tables['model_has_permissions']] as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$teamKey}` BIGINT UNSIGNED NOT NULL");
        }
    }
};
