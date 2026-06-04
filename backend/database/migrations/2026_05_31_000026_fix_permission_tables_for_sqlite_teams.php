<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite cannot ALTER columns easily; rebuild pivot tables with nullable team key for tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'workspace_id');
        $tables = config('permission.table_names');
        $pivotRole = config('permission.column_names.role_pivot_key') ?? 'role_id';
        $pivotPermission = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
        $modelKey = config('permission.column_names.model_morph_key', 'model_id');

        Schema::dropIfExists($tables['model_has_roles']);
        Schema::create($tables['model_has_roles'], static function (Blueprint $table) use ($tables, $teamKey, $pivotRole, $modelKey): void {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($modelKey);
            $table->unsignedBigInteger($teamKey)->nullable();
            $table->index($teamKey, 'model_has_roles_team_foreign_key_index');
            $table->foreign($pivotRole)->references('id')->on($tables['roles'])->onDelete('cascade');
            $table->primary([$teamKey, $pivotRole, $modelKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::dropIfExists($tables['model_has_permissions']);
        Schema::create($tables['model_has_permissions'], static function (Blueprint $table) use ($tables, $teamKey, $pivotPermission, $modelKey): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($modelKey);
            $table->unsignedBigInteger($teamKey)->nullable();
            $table->index($teamKey, 'model_has_permissions_team_foreign_key_index');
            $table->foreign($pivotPermission)->references('id')->on($tables['permissions'])->onDelete('cascade');
            $table->primary([$teamKey, $pivotPermission, $modelKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });
    }

    public function down(): void
    {
        // No-op: base permission migration defines original schema.
    }
};
