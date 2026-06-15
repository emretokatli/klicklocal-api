<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            // Per-platform publish options (e.g. TikTok privacy_level, commercial
            // content disclosure toggles) stored under metadata.{platform}.
            $table->json('metadata')->nullable()->after('media_id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
    }
};
