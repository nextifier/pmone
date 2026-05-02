<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['title', 'bio']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->string('title')->nullable()->after('slug');
            $table->text('bio')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['title', 'bio']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->json('title')->nullable()->after('slug');
            $table->json('bio')->nullable()->after('title');
        });
    }
};
