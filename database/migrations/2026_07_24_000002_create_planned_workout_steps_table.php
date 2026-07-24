<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_workout_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planned_workout_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('repeat')->default(1);
            $table->string('intensity'); // recovery | easy | steady | hard | max
            $table->unsignedSmallInteger('duration_min');
            $table->unsignedSmallInteger('recovery_min')->nullable();
            $table->string('recovery_intensity')->nullable();
            $table->string('label')->nullable();

            $table->index(['planned_workout_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_workout_steps');
    }
};
