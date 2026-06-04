<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('metric');
            $table->unsignedBigInteger('quantity')->default(1);
            $table->json('meta')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['workspace_id', 'type', 'recorded_at']);
            $table->index(['user_id', 'type', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
