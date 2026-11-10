<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KYC (specification section 2): each submission a member makes, its answers
 * and private files, and the admin's decision. A rejected member submits again,
 * so earlier submissions stay as a record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // unverified | pending | verified
            $table->string('kyc_status', 12)->default('unverified')->after('mobile_verified_at');
        });

        Schema::create('kyc_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            // The answers, keyed by field. A file answer holds its private storage path.
            $table->json('answers');
            // The form as it stood when submitted, so later changes to the form cannot change its meaning.
            $table->json('fields');
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_submissions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('kyc_status'));
    }
};
