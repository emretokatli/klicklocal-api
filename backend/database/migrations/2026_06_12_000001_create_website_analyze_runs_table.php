<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_analyze_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('website');
            $table->string('status', 32)->default('pending');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('partial')->default(false);
            $table->decimal('total_cost_usd', 10, 4)->nullable();
            $table->unsignedSmallInteger('num_turns')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_analyze_runs');
    }
};
