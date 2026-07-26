<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_workouts', function (Blueprint $table) {
            $table->timestamp('generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('planned_workouts', function (Blueprint $table) {
            $table->dropColumn('generated_at');
        });
    }
};
