<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            // Cached full website analysis (klicklocal-webanalyze schema) so it is
            // not recomputed on every dashboard load. website_analysis_url records
            // which URL produced it, so a changed website triggers a re-analysis.
            $table->json('website_analysis')->nullable()->after('website');
            $table->string('website_analysis_url')->nullable()->after('website_analysis');
            $table->timestamp('website_analyzed_at')->nullable()->after('website_analysis_url');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->dropColumn(['website_analysis', 'website_analysis_url', 'website_analyzed_at']);
        });
    }
};
