<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Awards (specification 5.8 and 5.11): recognition ranks and repurchase
 * award tiers. Each is earned once per member: the unique index makes sure
 * of it, however often the nightly check runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 12);                    // rank, repurchase
            $table->string('code', 40);                    // the rank's key, or the tier's PV
            $table->string('name', 80);
            $table->unsignedBigInteger('amount');          // kobo: the cash award, or a repurchase tier's increment
            $table->string('gift')->nullable();            // offered instead of the cash
            $table->string('choice', 8)->default('cash');  // cash or gift, chosen by the member while it waits
            $table->unsignedBigInteger('measure');         // the PV that qualified it (lesser leg, or team repurchase), whole PV
            $table->string('status', 12);                  // pending, paid, held, gift, delivered, rejected
            $table->string('reason')->nullable();          // why it was refused
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'kind', 'code']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
