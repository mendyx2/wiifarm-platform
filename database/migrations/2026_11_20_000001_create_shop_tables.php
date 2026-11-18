<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products, orders and pickup codes (specification section 8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('sku', 40)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('member_price'); // kobo per unit
            $table->unsignedBigInteger('public_price'); // kobo per unit, public shop
            $table->unsignedInteger('pv'); // hundredths of a PV per unit (1.5 PV = 150)
            $table->unsignedSmallInteger('units_per_pack')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique()->nullable();
            $table->string('kind', 12); // registration, repurchase or public
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('buyer_name', 120)->nullable(); // public buyers
            $table->string('buyer_phone', 30)->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('status', 12)->default('pending'); // pending, paid, fulfilled, cancelled
            $table->unsignedBigInteger('amount'); // kobo
            $table->unsignedInteger('pv'); // hundredths
            $table->unsignedInteger('units');
            $table->string('paid_with', 30)->nullable(); // main, product, package, online
            $table->string('payment_key', 191)->nullable()->unique();
            $table->string('pickup_code_hash', 64)->nullable()->unique();
            $table->text('pickup_code')->nullable(); // encrypted; only the buyer is shown it
            $table->string('pickup_code_hint', 4)->nullable(); // last 4 characters, for support
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['status', 'kind']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->restrictOnDelete();
            $table->unsignedInteger('quantity'); // units
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('unit_pv');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
};
