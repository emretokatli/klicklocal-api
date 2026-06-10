<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedupe guard for synced comments. external_id is nullable (manual
     * comments via POST /comments never set it) and both MySQL and SQLite
     * allow multiple NULLs in a unique index, so manual rows are unaffected.
     */
    public function up(): void
    {
        // Defensive: drop duplicate synced rows (keep the oldest) so the
        // unique index can be created on databases that already have data.
        $duplicateIds = DB::table('comments as c')
            ->whereNotNull('c.external_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('comments as earlier')
                    ->whereColumn('earlier.platform', 'c.platform')
                    ->whereColumn('earlier.external_id', 'c.external_id')
                    ->whereColumn('earlier.id', '<', 'c.id');
            })
            ->pluck('c.id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('comments')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('comments', function (Blueprint $table) {
            $table->unique(['platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropUnique(['platform', 'external_id']);
        });
    }
};
