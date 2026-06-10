<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->string('platform')->default('instagram')->after('prompt');
            $table->string('content_type')->default('post')->after('platform');
            $table->string('seo_focus')->nullable()->after('content_type');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropColumn(['platform', 'content_type', 'seo_focus']);
        });
    }
};
