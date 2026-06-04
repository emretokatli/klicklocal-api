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

        DB::statement(
            "ALTER TABLE posts MODIFY COLUMN status ENUM('draft', 'scheduled', 'processing', 'published', 'failed') NOT NULL DEFAULT 'draft'",
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE posts MODIFY COLUMN status ENUM('draft', 'scheduled', 'published', 'failed') NOT NULL DEFAULT 'draft'",
        );
    }
};
