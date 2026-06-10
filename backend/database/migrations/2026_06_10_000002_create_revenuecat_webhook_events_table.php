<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenuecat_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('type', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenuecat_webhook_events');
    }
};
