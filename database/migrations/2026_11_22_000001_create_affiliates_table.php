<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliates (specification section 8): anyone, member or not, can register.
 * An affiliate has their own sign-in and their own wallet in the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique(); // the referral code in their links, e.g. WII-7K3M9Q
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email')->unique();
            $table->string('phone', 30);
            $table->string('password');
            $table->string('status', 10)->default('active'); // active or banned
            $table->string('ban_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->after('user_id')->constrained('affiliates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('affiliate_id'));
        Schema::dropIfExists('affiliates');
    }
};
