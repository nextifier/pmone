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
        Schema::create('ticket_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Nullable: schema-level future-proofing for an event-level waitlist
            // (Plan 021 - overall event capacity, not yet implemented). Every
            // entry created by this plan's WaitlistService::join() always sets
            // a concrete ticket_id - the whole-ticket-quantity scope this plan
            // ships.
            $table->foreignId('ticket_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('waiting');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->string('claim_token')->nullable()->unique();
            $table->timestamps();

            // One live queue slot per ticket+buyer - re-joining resolves to the
            // same row (see WaitlistService::join()) instead of a duplicate.
            $table->unique(['ticket_id', 'email']);
            // FIFO "next waiting entry for this ticket" lookup (offerReleasedSeats).
            $table->index(['ticket_id', 'status', 'position']);
            // Stale-offer sweep (expireStaleOffers): all currently-offered
            // entries ordered by expiry, across every ticket.
            $table->index(['status', 'offer_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_waitlist_entries');
    }
};
