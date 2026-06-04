<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'media_id')) {
                $table->foreignId('media_id')->nullable()->after('content')->constrained('media')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            if (Schema::hasColumn('posts', 'media_id')) {
                $table->dropConstrainedForeignId('media_id');
            }
        });
    }
};
