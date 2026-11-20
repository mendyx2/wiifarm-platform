<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member messages (specification section 12): an admin's changes to each
 * message (the defaults are in config/messages.php), and a log of every
 * message sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40)->unique();
            $table->string('subject', 200);
            $table->text('body');
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('message_log', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 255);
            $table->string('subject', 200);
            $table->string('status', 10);    // queued, sent, failed
            $table->timestamps();

            $table->index(['event', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_log');
        Schema::dropIfExists('message_templates');
    }
};
