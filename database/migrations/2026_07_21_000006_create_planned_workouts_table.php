<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->string('sport'); // run | bike | other
            $table->string('title');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();

            // Set once the workout has been pushed into chronos.
            $table->string('chronos_event_id')->nullable();
            $table->string('chronos_url')->nullable();
            $table->timestamp('pushed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_workouts');
    }
};
