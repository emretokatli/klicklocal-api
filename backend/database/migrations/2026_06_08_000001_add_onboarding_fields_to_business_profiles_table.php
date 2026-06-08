<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->string('website', 500)->nullable()->after('products_services');
            $table->string('team_size', 60)->nullable()->after('website');
            $table->string('monthly_revenue', 60)->nullable()->after('team_size');
            $table->string('customer_source', 120)->nullable()->after('monthly_revenue');
            $table->json('social_media_channels')->nullable()->after('customer_source');
            $table->text('target_audience')->nullable()->after('social_media_channels');
            $table->text('unique_value_proposition')->nullable()->after('target_audience');
            $table->text('additional_notes')->nullable()->after('unique_value_proposition');
            $table->string('primary_goal', 120)->nullable()->after('additional_notes');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'website',
                'team_size',
                'monthly_revenue',
                'customer_source',
                'social_media_channels',
                'target_audience',
                'unique_value_proposition',
                'additional_notes',
                'primary_goal',
            ]);
        });
    }
};
