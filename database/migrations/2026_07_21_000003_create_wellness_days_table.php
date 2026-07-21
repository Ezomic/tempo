<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wellness_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('date');

            $table->unsignedSmallInteger('sleep_score')->nullable();
            $table->unsignedInteger('sleep_duration_s')->nullable();

            $table->string('hrv_status')->nullable(); // balanced | unbalanced | low | poor
            $table->unsignedSmallInteger('hrv_last_night_ms')->nullable();
            $table->unsignedSmallInteger('hrv_baseline_low')->nullable();
            $table->unsignedSmallInteger('hrv_baseline_high')->nullable();

            $table->unsignedSmallInteger('body_battery_high')->nullable();
            $table->unsignedSmallInteger('body_battery_low')->nullable();

            $table->unsignedSmallInteger('resting_hr')->nullable();
            $table->unsignedSmallInteger('stress_avg')->nullable();

            $table->json('raw')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellness_days');
    }
};
