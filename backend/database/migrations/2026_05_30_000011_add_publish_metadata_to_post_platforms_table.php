<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            $table->string('platform_post_id')->nullable()->after('status');
            $table->text('failure_reason')->nullable()->after('response_data');
        });
    }

    public function down(): void
    {
        Schema::table('post_platforms', function (Blueprint $table) {
            $table->dropColumn(['platform_post_id', 'failure_reason']);
        });
    }
};
