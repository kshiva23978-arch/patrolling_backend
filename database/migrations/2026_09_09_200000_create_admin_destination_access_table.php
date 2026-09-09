<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which destinations a Department Admin or Ranger admin account is
     * scoped to — the admin-table equivalent of `user_beach_access`. Only
     * consulted for an admin whose role has `ro_level` `department_admin`
     * or `ranger`; a `master_admin`-level (or role-less) admin ignores
     * this table entirely and sees every destination.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_destination_access')) {
            return;
        }

        Schema::create('admin_destination_access', function (Blueprint $table) {
            $table->uuid('ada_admin_id');
            $table->uuid('ada_destination_id');

            $table->foreign('ada_admin_id')->references('a_id')->on('admins')->cascadeOnDelete();
            $table->foreign('ada_destination_id')->references('ds_id')->on('destinations')->cascadeOnDelete();
            $table->primary(['ada_admin_id', 'ada_destination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_destination_access');
    }
};
