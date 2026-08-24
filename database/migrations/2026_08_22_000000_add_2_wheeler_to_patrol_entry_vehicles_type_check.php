<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `patrol_entry_vehicles.pev_vehicle_type` was created (see
 * 2026_08_17_223020_create_patrol_entry_vehicles_table) allowing only
 * `4_wheeler`/`boat` — `2_wheeler` was never added to the check constraint,
 * even though the app has always validated and sent it as a valid vehicle
 * type (see PatrolEntryController), causing every 2-wheeler patrol vehicle
 * to fail with a Postgres check-constraint violation on insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patrol_entry_vehicles')) {
            return;
        }

        DB::statement('ALTER TABLE patrol_entry_vehicles DROP CONSTRAINT IF EXISTS patrol_entry_vehicles_pev_vehicle_type_check');
        DB::statement("ALTER TABLE patrol_entry_vehicles ADD CONSTRAINT patrol_entry_vehicles_pev_vehicle_type_check CHECK (pev_vehicle_type IN ('2_wheeler', '4_wheeler', 'boat'))");
    }

    public function down(): void
    {
        if (! Schema::hasTable('patrol_entry_vehicles')) {
            return;
        }

        DB::statement('ALTER TABLE patrol_entry_vehicles DROP CONSTRAINT IF EXISTS patrol_entry_vehicles_pev_vehicle_type_check');
        DB::statement("ALTER TABLE patrol_entry_vehicles ADD CONSTRAINT patrol_entry_vehicles_pev_vehicle_type_check CHECK (pev_vehicle_type IN ('4_wheeler', 'boat'))");
    }
};
