<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->text('suggested_reply')->nullable()->after('sentiment_classified_at');
            $table->text('reply_text')->nullable()->after('suggested_reply');
            $table->timestamp('replied_at')->nullable()->after('reply_text');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn(['suggested_reply', 'reply_text', 'replied_at']);
        });
    }
};
