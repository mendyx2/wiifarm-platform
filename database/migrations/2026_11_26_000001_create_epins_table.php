<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * E-pins (specification section 6): a code worth a fixed amount that pays
 * one registration, once. Bought by a member from their VISA wallet, or
 * created by an admin. The code is kept encrypted, with a keyed fingerprint
 * to find it, so it is never stored in plain text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epins', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->text('code_encrypted');
            $table->unsignedBigInteger('amount');                  // kobo
            $table->foreignId('package_id')->nullable()->constrained()->restrictOnDelete(); // bought for this package
            $table->string('status', 10);                          // active, used, cancelled
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('reason')->nullable();                  // why an admin created it
            $table->string('batch', 64);                           // the form that created it: sent twice, created once
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('cancelled_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('batch');
            $table->index(['created_by_user_id', 'id']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epins');
    }
};
