<?php

use App\Support\TeamContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $teamKey = config('permission.column_names.team_foreign_key', 'workspace_id');
        $tables = config('permission.table_names');

        DB::table($tables['roles'])->whereNull($teamKey)->update([$teamKey => TeamContext::PLATFORM]);
        DB::table($tables['model_has_roles'])->whereNull($teamKey)->update([$teamKey => TeamContext::PLATFORM]);
        DB::table($tables['model_has_permissions'])->whereNull($teamKey)->update([$teamKey => TeamContext::PLATFORM]);
    }

    public function down(): void
    {
        // Irreversible without data loss.
    }
};
