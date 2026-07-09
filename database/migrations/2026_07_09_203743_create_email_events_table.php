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
        /**
         * Deliberately no foreign key to email_messages: SES also emits events
         * for messages this application never recorded (sent from another
         * mailer, or before this table existed). Losing those would hide real
         * bounces, so events stand on their own and join on message_id.
         */
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->index();
            $table->string('type');
            // Empty string rather than null: Postgres treats NULLs as distinct,
            // so a nullable column would let SNS's at-least-once redeliveries
            // slip past the dedupe index for events that carry no recipient.
            $table->string('recipient')->default('');
            $table->string('subtype')->nullable();
            $table->text('diagnostic')->nullable();
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('occurred_at');
            $table->unique(['message_id', 'type', 'recipient', 'occurred_at'], 'email_events_dedupe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
