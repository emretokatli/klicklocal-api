<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_audio', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('platform', 32)->nullable();
            $table->string('external_ref')->nullable();
            $table->string('source', 64)->default('fake');
            $table->timestamps();

            $table->index('platform');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_audio');
    }
};
