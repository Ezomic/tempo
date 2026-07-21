<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('external_id'); // Garmin activity id
            $table->string('sport'); // run | bike | other
            $table->string('sub_sport')->nullable();

            $table->timestamp('started_at');
            $table->string('timezone')->nullable();

            $table->unsignedInteger('duration_s')->nullable();
            $table->unsignedInteger('moving_time_s')->nullable();
            $table->float('distance_m')->nullable();
            $table->unsignedSmallInteger('avg_hr')->nullable();
            $table->unsignedSmallInteger('max_hr')->nullable();
            $table->float('elevation_gain_m')->nullable();
            $table->float('avg_speed_mps')->nullable();
            $table->unsignedInteger('calories')->nullable();

            $table->float('trimp')->nullable();
            $table->json('hr_zone_seconds')->nullable();

            $table->string('fit_path')->nullable();
            $table->json('raw_summary')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'external_id']);
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
