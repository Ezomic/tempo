<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_workouts', function (Blueprint $table) {
            $table->string('workout_type')->nullable()->after('sport');
        });
    }

    public function down(): void
    {
        Schema::table('planned_workouts', function (Blueprint $table) {
            $table->dropColumn('workout_type');
        });
    }
};
