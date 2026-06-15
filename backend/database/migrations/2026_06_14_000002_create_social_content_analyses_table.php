<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_content_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_id');
            $table->string('post_type')->nullable(); // image | video | reel | carousel | text
            $table->text('caption')->nullable();
            $table->string('permalink', 1024)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('hour')->nullable(); // 0-23, publishing hour
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('engagement')->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
            $table->index(['workspace_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_content_analyses');
    }
};
