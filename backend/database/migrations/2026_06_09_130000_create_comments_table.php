<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('platform', ['instagram', 'tiktok', 'facebook', 'linkedin']);
            $table->string('external_id')->nullable();
            $table->string('author');
            $table->text('text');
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->default('neutral');
            $table->timestamp('commented_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
