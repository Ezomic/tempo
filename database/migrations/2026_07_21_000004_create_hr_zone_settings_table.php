<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_zone_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('max_hr')->nullable();
            $table->unsignedSmallInteger('resting_hr')->nullable();
            $table->unsignedSmallInteger('lthr')->nullable();

            // Ordered bpm thresholds between zones; drives per-zone breakdown.
            $table->json('zone_boundaries')->nullable();

            // Selects the Banister TRIMP weighting constant (male 1.92 / female 1.67).
            $table->string('sex')->default('male');

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_zone_settings');
    }
};
