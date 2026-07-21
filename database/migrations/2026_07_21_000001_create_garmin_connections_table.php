<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garmin_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // disconnected | connected. Tokens live in the sidecar, keyed by id.
            $table->string('status')->default('disconnected');
            $table->string('garmin_display_name')->nullable();

            $table->string('sync_status')->default('idle'); // idle | syncing | error
            $table->timestamp('sync_status_since')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garmin_connections');
    }
};
