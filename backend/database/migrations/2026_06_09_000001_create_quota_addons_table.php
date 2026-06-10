<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->integer('amount');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('purchased_at')->useCurrent();
            $table->decimal('price_paid', 10, 2);
            $table->string('provider')->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_addons');
    }
};
