<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_workouts', function (Blueprint $table) {
            $table->string('garmin_workout_id')->nullable()->after('chronos_url');
            $table->timestamp('garmin_pushed_at')->nullable()->after('garmin_workout_id');
        });
    }

    public function down(): void
    {
        Schema::table('planned_workouts', function (Blueprint $table) {
            $table->dropColumn(['garmin_workout_id', 'garmin_pushed_at']);
        });
    }
};
