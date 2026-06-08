<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('onboarding_step', 60)->default('get-started')->after('avatar');
            $table->json('onboarding_data')->nullable()->after('onboarding_step');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_data');
        });

        DB::table('users')
            ->whereNull('onboarding_completed_at')
            ->update([
                'onboarding_completed_at' => now(),
                'onboarding_step' => 'completed',
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_step',
                'onboarding_data',
                'onboarding_completed_at',
            ]);
        });
    }
};
