<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_hashtags', function (Blueprint $table): void {
            $table->id();
            $table->string('tag');
            $table->string('category', 64)->nullable();
            $table->string('volume_label', 32)->nullable();
            $table->string('source', 64)->default('fake');
            $table->timestamps();

            $table->index('category');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_hashtags');
    }
};
