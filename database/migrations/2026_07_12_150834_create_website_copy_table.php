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
        Schema::create('website_copy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // A flat dot-path key, e.g. "pages.home.title" or
            // "pages.brands.description" - mirrors the pages.*/components.*
            // dot-path convention already used by pmone-events' content.js, so a
            // future rollout can add component-body keys without a schema
            // change. Kept as a plain string (not a DB enum), mirroring
            // App\Models\WebsitePage::KEYS - validation stays app-level. See
            // App\Models\WebsiteCopy::PAGE_KEYS / FIELDS for the spike's
            // whitelist (plan 012).
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_copy');
    }
};
