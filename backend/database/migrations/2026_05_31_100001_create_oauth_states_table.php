<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_states', function (Blueprint $table): void {
            $table->id();
            $table->string('state', 64)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['provider', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_states');
    }
};
