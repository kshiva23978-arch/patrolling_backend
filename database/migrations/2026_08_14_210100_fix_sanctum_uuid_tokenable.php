<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('personal_access_tokens', 'tokenable_id')) {
            DB::statement('DROP INDEX IF EXISTS personal_access_tokens_tokenable_type_tokenable_id_index');
            DB::statement('ALTER TABLE personal_access_tokens DROP COLUMN tokenable_id');
            DB::statement('ALTER TABLE personal_access_tokens DROP COLUMN tokenable_type');
            DB::statement('ALTER TABLE personal_access_tokens ADD COLUMN tokenable_id UUID');
            DB::statement('ALTER TABLE personal_access_tokens ADD COLUMN tokenable_type VARCHAR(255)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('personal_access_tokens', 'tokenable_id')) {
            DB::statement('DROP INDEX IF EXISTS personal_access_tokens_tokenable_type_tokenable_id_index');
            DB::statement('ALTER TABLE personal_access_tokens DROP COLUMN tokenable_id');
            DB::statement('ALTER TABLE personal_access_tokens DROP COLUMN tokenable_type');
            DB::statement('ALTER TABLE personal_access_tokens ADD COLUMN tokenable_id BIGINT');
            DB::statement('ALTER TABLE personal_access_tokens ADD COLUMN tokenable_type VARCHAR(255)');
        }
    }
};
