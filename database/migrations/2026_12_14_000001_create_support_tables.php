<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tickets (specification section 11): a member writes, Wiifarm's
 * admins reply, either side can attach a file (kept private), and a ticket is
 * closed when done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('subject', 150);
            $table->string('status', 10)->default('open');   // open (waiting for Wiifarm), answered (waiting for the member), closed
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
            $table->index(['user_id', 'id']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();   // null: written by the member
            $table->text('body');
            $table->string('attachment_path')->nullable();     // private
            $table->string('attachment_name', 150)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
