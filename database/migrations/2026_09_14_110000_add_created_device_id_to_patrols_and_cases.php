<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamps every patrol/case with the phone that created it (the app's
 * `X-Device-Id` header — see App\Support\DeviceIdentity) so unfinished work
 * stays private to that phone until it's uploaded. Nullable: rows created
 * before this fall back to their login-token stamp for the same check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->string('pe_created_device_id', 64)->nullable()->after('pe_created_via_token_id')->index();
        });
        Schema::table('case_entries', function (Blueprint $table) {
            $table->string('ce_created_device_id', 64)->nullable()->after('ce_created_via_token_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropColumn('pe_created_device_id');
        });
        Schema::table('case_entries', function (Blueprint $table) {
            $table->dropColumn('ce_created_device_id');
        });
    }
};
