<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every change of a member's sponsor, kept for good (specification section 2:
 * "Each change is logged with the old and new sponsor").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('old_sponsor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('new_sponsor_id')->constrained('users')->restrictOnDelete();
            // The admin who made it (admins arrive in phase 2d; kept as a plain id until then).
            $table->unsignedBigInteger('changed_by_admin_id')->nullable()->index();
            $table->string('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_changes');
    }
};
