<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->timestamp('fit_failed_at')->nullable();
            $table->string('fit_error')->nullable();
            $table->index(['user_id', 'fit_failed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'fit_failed_at']);
            $table->dropColumn(['fit_failed_at', 'fit_error']);
        });
    }
};
