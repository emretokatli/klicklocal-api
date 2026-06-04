<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('social_accounts', 'account_name')) {
                $table->string('account_name')->nullable()->after('provider_account_id');
            }
            if (! Schema::hasColumn('social_accounts', 'status')) {
                $table->string('status', 32)->default('disconnected')->after('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropColumn(['account_name', 'status']);
        });
    }
};
