<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_workouts', function (Blueprint $table) {
            $table->json('route_geometry')->nullable()->after('notes');
            $table->unsignedInteger('route_distance_m')->nullable()->after('route_geometry');
            $table->unsignedInteger('route_ascent_m')->nullable()->after('route_distance_m');
            $table->string('route_kind')->nullable()->after('route_ascent_m');
        });
    }

    public function down(): void
    {
        Schema::table('planned_workouts', function (Blueprint $table) {
            $table->dropColumn(['route_geometry', 'route_distance_m', 'route_ascent_m', 'route_kind']);
        });
    }
};
