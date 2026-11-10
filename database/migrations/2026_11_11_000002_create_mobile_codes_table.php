<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The current phone-verification code of each member: only its hash is kept,
 * with when it expires and how many wrong tries have been made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_codes', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->string('mobile', 30);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_codes');
    }
};
