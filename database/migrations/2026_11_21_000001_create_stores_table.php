<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stockist stores (specification section 9). A store's wallet and stock are
 * ledger accounts; this table holds who runs it, where, and its standing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // the stockist (a member)
            $table->string('kind', 4); // mega or sub
            $table->foreignId('mega_store_id')->nullable()->constrained('stores')->restrictOnDelete(); // a Sub's Mega
            $table->foreignId('sponsor_id')->constrained('users')->restrictOnDelete(); // generation 1 of the sponsor bonus
            $table->string('name', 120);
            $table->string('state', 80);
            $table->string('address');
            $table->string('phone', 30);
            $table->string('status', 12)->default('pending'); // pending, active, deactivated
            $table->unsignedBigInteger('deposit'); // kobo
            $table->string('deposit_reference', 191)->nullable()->unique();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'kind']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('fulfilled_by_store_id')->nullable()->after('fulfilled_at')->constrained('stores')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('fulfilled_by_store_id'));
        Schema::dropIfExists('stores');
    }
};
