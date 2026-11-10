<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Members (specification section 2). Admins are a separate table.
            $table->id();
            $table->string('username', 40)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('dial_code', 6);
            $table->string('mobile', 20);
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('country', 2);
            $table->string('state', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('address')->nullable();
            // The member who referred them. Only the company account (member #1) has none.
            $table->foreignId('sponsor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 10)->default('active'); // active | banned
            $table->string('ban_reason')->nullable();
            $table->boolean('withdrawals_blocked')->default(false);
            $table->string('withdrawals_block_reason')->nullable();
            // Set when the package is paid: until then the account is pending and earns nothing.
            $table->timestamp('activated_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            $table->index(['sponsor_id', 'status']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
