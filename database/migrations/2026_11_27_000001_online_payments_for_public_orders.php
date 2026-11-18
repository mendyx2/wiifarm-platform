<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Public-shop orders are paid online too (specification section 8). Their
 * buyers have no account, so an online payment may belong to an order
 * instead of a member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('online_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
