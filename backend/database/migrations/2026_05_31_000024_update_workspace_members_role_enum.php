<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('workspace_members')->where('role', 'admin')->update(['role' => 'manager']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE workspace_members MODIFY role ENUM('owner', 'manager', 'editor', 'viewer') NOT NULL DEFAULT 'editor'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE workspace_members MODIFY role ENUM('owner', 'admin', 'editor', 'viewer') NOT NULL DEFAULT 'editor'");
        }

        DB::table('workspace_members')->where('role', 'manager')->update(['role' => 'admin']);
    }
};
