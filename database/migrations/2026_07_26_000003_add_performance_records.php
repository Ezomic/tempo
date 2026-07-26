<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-activity bests, computed at parse time from the full-resolution
        // speed stream. distance => seconds, and duration => m/s.
        Schema::table('activities', function (Blueprint $table) {
            $table->json('best_efforts')->nullable();
            $table->json('mean_max')->nullable();
        });

        // Fastest time per standard running distance (the envelope across all
        // activities) so the records page never rescans every activity.
        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('distance_m');
            $table->unsignedInteger('duration_s');
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->date('achieved_on');
            $table->timestamps();

            $table->unique(['user_id', 'distance_m']);
        });

        // Best sustained speed per duration, per sport (mean-max envelope).
        Schema::create('mean_max_efforts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sport');
            $table->unsignedInteger('duration_s');
            $table->float('speed_mps');
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->date('achieved_on');
            $table->timestamps();

            $table->unique(['user_id', 'sport', 'duration_s']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mean_max_efforts');
        Schema::dropIfExists('personal_records');
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['best_efforts', 'mean_max']);
        });
    }
};
