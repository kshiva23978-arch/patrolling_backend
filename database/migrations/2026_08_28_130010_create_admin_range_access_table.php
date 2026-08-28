<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which ranges a Department Admin or Ranger admin account is scoped
     * to — the admin-table equivalent of `user_range_access`. Only
     * consulted for an admin whose role has `ro_level` `department_admin`
     * or `ranger`; a `master_admin`-level (or role-less) admin ignores
     * this table entirely and sees every range.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_range_access')) {
            return;
        }

        Schema::create('admin_range_access', function (Blueprint $table) {
            $table->uuid('ara_admin_id');
            $table->uuid('ara_range_id');

            $table->foreign('ara_admin_id')->references('a_id')->on('admins')->cascadeOnDelete();
            $table->foreign('ara_range_id')->references('rn_id')->on('ranges')->cascadeOnDelete();
            $table->primary(['ara_admin_id', 'ara_range_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_range_access');
    }
};
