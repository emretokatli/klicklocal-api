<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // NULL = not yet classified by the AI sentiment classifier.
            $table->timestamp('sentiment_classified_at')->nullable()->after('sentiment');
            $table->index(['workspace_id', 'sentiment_classified_at']);
        });

        // Backfill: rows with a non-default sentiment can only exist because a
        // manual POST /comments explicitly provided one (sync always leaves the
        // 'neutral' DB default), so mark them as already classified. Rows at
        // 'neutral' are indistinguishable from unset defaults and stay NULL —
        // re-classifying an explicit 'neutral' is harmless and cheap.
        DB::table('comments')
            ->whereIn('sentiment', ['positive', 'negative'])
            ->update(['sentiment_classified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'sentiment_classified_at']);
            $table->dropColumn('sentiment_classified_at');
        });
    }
};
