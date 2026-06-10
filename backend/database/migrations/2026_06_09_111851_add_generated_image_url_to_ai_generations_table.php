<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->string('generated_image_url')->nullable()->after('raw_response');
            $table->string('image_model')->nullable()->after('generated_image_url');
            $table->string('image_revised_prompt')->nullable()->after('image_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropColumn(['generated_image_url', 'image_model', 'image_revised_prompt']);
        });
    }
};
