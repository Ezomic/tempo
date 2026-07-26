<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // ctl | race_time
            $table->float('target_value'); // CTL number, or race finish in seconds
            $table->unsignedInteger('distance_m')->nullable(); // race_time only
            $table->date('target_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_goals');
    }
};
