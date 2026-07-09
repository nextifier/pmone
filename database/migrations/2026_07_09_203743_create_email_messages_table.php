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
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('mailer')->default('ses-v2');
            $table->string('from_address');
            $table->string('subject')->nullable();
            $table->json('recipients');
            $table->string('configuration_set')->nullable();
            $table->string('status')->default('send');
            $table->unsignedTinyInteger('status_rank')->default(1);
            $table->timestamp('sent_at');
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
