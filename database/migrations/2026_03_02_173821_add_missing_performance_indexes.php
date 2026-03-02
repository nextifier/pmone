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
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('event_product_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('visibility');
            $table->index('parent_id');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->index('type');
        });

        Schema::table('category_post', function (Blueprint $table) {
            $table->index('post_id');
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('task_user', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['event_product_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['visibility']);
            $table->dropIndex(['parent_id']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex(['type']);
        });

        Schema::table('category_post', function (Blueprint $table) {
            $table->dropIndex(['post_id']);
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('task_user', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
