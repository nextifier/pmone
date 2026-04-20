<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $dropables = [
                'category',
                'website_url',
                'children_policy',
                'nearest_airport',
                'airport_distance_km',
                'latitude',
                'longitude',
                'facilities',
            ];

            foreach ($dropables as $column) {
                if (Schema::hasColumn('hotels', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('hotels', function (Blueprint $table) {
            if (! Schema::hasColumn('hotels', 'settings')) {
                $table->jsonb('settings')->nullable()->after('service_charge_percentage');
            }
            if (! Schema::hasColumn('hotels', 'more_details')) {
                $table->jsonb('more_details')->nullable()->after('settings');
            }
            if (! Schema::hasColumn('hotels', 'order_column')) {
                $table->integer('order_column')->nullable()->after('more_details');
            }
        });

        Schema::table('room_types', function (Blueprint $table) {
            $dropables = ['view_type', 'amenities'];

            foreach ($dropables as $column) {
                if (Schema::hasColumn('room_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('room_types', function (Blueprint $table) {
            if (! Schema::hasColumn('room_types', 'settings')) {
                $table->jsonb('settings')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('room_types', 'more_details')) {
                $table->jsonb('more_details')->nullable()->after('settings');
            }
            if (! Schema::hasColumn('room_types', 'order_column')) {
                $table->integer('order_column')->nullable()->after('more_details');
            }
        });

        Schema::table('hotel_event_allotments', function (Blueprint $table) {
            if (! Schema::hasColumn('hotel_event_allotments', 'settings')) {
                $table->jsonb('settings')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('hotel_event_allotments', 'more_details')) {
                $table->jsonb('more_details')->nullable()->after('settings');
            }
            if (! Schema::hasColumn('hotel_event_allotments', 'order_column')) {
                $table->integer('order_column')->nullable()->after('more_details');
            }
        });

        Schema::table('hotel_transfer_options', function (Blueprint $table) {
            if (! Schema::hasColumn('hotel_transfer_options', 'settings')) {
                $table->jsonb('settings')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('hotel_transfer_options', 'more_details')) {
                $table->jsonb('more_details')->nullable()->after('settings');
            }
            if (! Schema::hasColumn('hotel_transfer_options', 'order_column')) {
                $table->integer('order_column')->nullable()->after('more_details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['settings', 'more_details', 'order_column']);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['settings', 'more_details', 'order_column']);
        });

        Schema::table('hotel_event_allotments', function (Blueprint $table) {
            $table->dropColumn(['settings', 'more_details', 'order_column']);
        });

        Schema::table('hotel_transfer_options', function (Blueprint $table) {
            $table->dropColumn(['settings', 'more_details', 'order_column']);
        });
    }
};
