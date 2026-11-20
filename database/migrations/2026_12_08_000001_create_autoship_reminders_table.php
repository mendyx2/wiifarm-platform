<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autoship reminders sent (specification 5.6): one row per member, month and
 * stage, so a reminder is never sent twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autoship_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);        // YYYY-MM the autoship is for
            $table->string('stage', 12);       // week4, first_week, last_day
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'month', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autoship_reminders');
    }
};
