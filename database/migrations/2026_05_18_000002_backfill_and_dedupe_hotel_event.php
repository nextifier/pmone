<?php

use App\Models\Hotel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill hotel_event pivot table from existing hotels.event_id, deduplicating
 * hotels by slug. For each slug group: keep first as canonical, redirect all FKs
 * from duplicates to canonical, soft-delete duplicates, then insert pivot rows
 * for every event_id that previously owned a hotel under this slug.
 *
 * IRREVERSIBLE: down() truncates hotel_event but cannot reconstruct duplicate
 * hotels. Restore from backup to recover.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $allHotels = DB::table('hotels')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            $grouped = $allHotels->groupBy('slug');

            foreach ($grouped as $slug => $hotels) {
                $canonical = $hotels->first();
                $duplicates = $hotels->slice(1);

                // Collect event_ids for pivot rows (canonical + each duplicate)
                $eventIds = collect([$canonical->event_id])
                    ->merge($duplicates->pluck('event_id'))
                    ->unique()
                    ->filter()
                    ->values()
                    ->all();

                foreach ($duplicates as $dup) {
                    // 1. Reservations -> canonical hotel
                    DB::table('reservations')
                        ->where('hotel_id', $dup->id)
                        ->update(['hotel_id' => $canonical->id]);

                    // 2. Room types: merge by (hotel_id, slug)
                    $dupRooms = DB::table('room_types')
                        ->where('hotel_id', $dup->id)
                        ->whereNull('deleted_at')
                        ->get();

                    foreach ($dupRooms as $room) {
                        $canonicalRoom = DB::table('room_types')
                            ->where('hotel_id', $canonical->id)
                            ->where('slug', $room->slug)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($canonicalRoom) {
                            // Redirect FKs pointing at the duplicate room
                            DB::table('reservation_items')
                                ->where('room_type_id', $room->id)
                                ->update(['room_type_id' => $canonicalRoom->id]);

                            DB::table('hotel_event_allotments')
                                ->where('room_type_id', $room->id)
                                ->update(['room_type_id' => $canonicalRoom->id]);

                            DB::table('room_type_pricing_periods')
                                ->where('room_type_id', $room->id)
                                ->update(['room_type_id' => $canonicalRoom->id]);

                            // Soft-delete merged room
                            DB::table('room_types')
                                ->where('id', $room->id)
                                ->update(['deleted_at' => now()]);
                        } else {
                            // Move room to canonical hotel
                            DB::table('room_types')
                                ->where('id', $room->id)
                                ->update(['hotel_id' => $canonical->id]);
                        }
                    }

                    // 3. Allotments: re-point hotel_id to canonical.
                    //    Unique constraint is (hotel_id, room_type_id, start_date, end_date)
                    //    On collision, prefer the first (older) allotment and soft-delete the second.
                    $dupAllotments = DB::table('hotel_event_allotments')
                        ->where('hotel_id', $dup->id)
                        ->whereNull('deleted_at')
                        ->get();

                    foreach ($dupAllotments as $alot) {
                        $collision = DB::table('hotel_event_allotments')
                            ->where('hotel_id', $canonical->id)
                            ->where('room_type_id', $alot->room_type_id)
                            ->where('start_date', $alot->start_date)
                            ->where('end_date', $alot->end_date)
                            ->whereNull('deleted_at')
                            ->where('id', '!=', $alot->id)
                            ->first();

                        if ($collision) {
                            // Soft-delete the duplicate allotment
                            DB::table('hotel_event_allotments')
                                ->where('id', $alot->id)
                                ->update(['deleted_at' => now()]);
                        } else {
                            DB::table('hotel_event_allotments')
                                ->where('id', $alot->id)
                                ->update(['hotel_id' => $canonical->id]);
                        }
                    }

                    // 4. Transfer options: dedupe by (label, direction) on canonical
                    $dupTransfers = DB::table('hotel_transfer_options')
                        ->where('hotel_id', $dup->id)
                        ->whereNull('deleted_at')
                        ->get();

                    foreach ($dupTransfers as $opt) {
                        $existing = DB::table('hotel_transfer_options')
                            ->where('hotel_id', $canonical->id)
                            ->where('label', $opt->label)
                            ->where('direction', $opt->direction)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($existing) {
                            DB::table('reservation_transfers')
                                ->where('transfer_option_id', $opt->id)
                                ->update(['transfer_option_id' => $existing->id]);
                            DB::table('hotel_transfer_options')
                                ->where('id', $opt->id)
                                ->update(['deleted_at' => now()]);
                        } else {
                            DB::table('hotel_transfer_options')
                                ->where('id', $opt->id)
                                ->update(['hotel_id' => $canonical->id]);
                        }
                    }

                    // 5. Media (spatie/media-library polymorphic)
                    DB::table('media')
                        ->where('model_type', Hotel::class)
                        ->where('model_id', $dup->id)
                        ->update(['model_id' => $canonical->id]);

                    // 6. Tags (spatie/tags polymorphic)
                    DB::table('taggables')
                        ->where('taggable_type', Hotel::class)
                        ->where('taggable_id', $dup->id)
                        ->update(['taggable_id' => $canonical->id]);

                    // 7. Soft-delete duplicate hotel
                    DB::table('hotels')
                        ->where('id', $dup->id)
                        ->update(['deleted_at' => now()]);
                }

                // Insert pivot rows for canonical x each event_id
                foreach ($eventIds as $eventId) {
                    DB::table('hotel_event')->insertOrIgnore([
                        'hotel_id' => $canonical->id,
                        'event_id' => $eventId,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Log::warning('Rolling back backfill_and_dedupe_hotel_event: pivot rows truncated, but duplicate hotels CANNOT be reconstructed from this migration. Restore from backup to recover deduped data.');
        DB::table('hotel_event')->truncate();
    }
};
