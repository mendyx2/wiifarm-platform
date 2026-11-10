<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packages and the binary network (specification sections 3 and 4).
 * PV itself is not stored here: it is ledger entries in PV (section 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->unsignedSmallInteger('sort');
            $table->unsignedBigInteger('price'); // kobo
            $table->unsignedInteger('pv'); // whole PV
            $table->unsignedSmallInteger('product_packs');
            $table->decimal('direct_bonus_percent', 5, 2); // of PV value
            $table->decimal('match_percent', 5, 2); // of one match's value
            $table->unsignedSmallInteger('daily_cap_matches');
            $table->unsignedTinyInteger('unilevel_generations');
            $table->boolean('upgradable')->default(true); // an admin can mark "no upgrades"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('sponsor_id')->constrained('packages')->restrictOnDelete();
            // The parent and leg asked for at sign-up (optional), used when the package is paid.
            $table->foreignId('requested_parent_id')->nullable()->after('package_id')->constrained('users')->nullOnDelete();
            $table->char('requested_leg', 1)->nullable()->after('requested_parent_id');
        });

        // One row per placed member. A slot (parent + leg) is unique, so two
        // members can never hold it: the database refuses the second.
        Schema::create('tree_positions', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('leg', 1)->nullable(); // L or R under the parent; null only for the top
            $table->unsignedInteger('depth');
            $table->timestamp('placed_at')->useCurrent();

            $table->unique(['parent_id', 'leg']);
            $table->index('depth');
        });

        // Joining and upgrades. The payment key is unique, so one payment
        // activates or upgrades once only.
        Schema::create('package_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 10); // join or upgrade
            $table->foreignId('from_package_id')->nullable()->constrained('packages')->restrictOnDelete();
            $table->foreignId('to_package_id')->constrained('packages')->restrictOnDelete();
            $table->string('payment_key', 191)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        // Problems found in the tree that an admin should look at (section 3).
        Schema::create('network_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('details');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_alerts');
        Schema::dropIfExists('package_changes');
        Schema::dropIfExists('tree_positions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_parent_id');
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn('requested_leg');
        });
        Schema::dropIfExists('packages');
    }
};
