<?php

namespace Database\Seeders;

use App\Models\PatrolTypes;
use App\Models\Ranges;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PatrolTypeSeeder extends Seeder
{
    /**
     * Seed the standard patrol type forms and the range categories each one applies to.
     */
    public function run(): void
    {
        $definitions = [
            'Vehicle Patrol Form' => [Ranges::CATEGORY_HQ, Ranges::CATEGORY_PROTECTION, Ranges::CATEGORY_LBWS],
            'Walking Patrol Form' => Ranges::CATEGORIES,
            'Boat Patrol Form' => [Ranges::CATEGORY_PROTECTION, Ranges::CATEGORY_MPNP, Ranges::CATEGORY_MGMNP, Ranges::CATEGORY_RESEARCH_SURVEY],
            'Quick Response Team Form' => [Ranges::CATEGORY_PROTECTION],
            'Wildlife Rescue Form' => [Ranges::CATEGORY_PROTECTION, Ranges::CATEGORY_LBWS],
            'Anti-Poaching Form' => Ranges::PROTECTED_AREA_CATEGORIES,
            'Marine Surveillance Form' => [Ranges::CATEGORY_MGMNP, Ranges::CATEGORY_MPNP, Ranges::CATEGORY_RESEARCH_SURVEY],
            'Crocodile Conflict Form' => [Ranges::CATEGORY_LBWS, Ranges::CATEGORY_PROTECTION],
        ];

        foreach ($definitions as $name => $categories) {
            $patrolType = PatrolTypes::firstOrCreate(['pt_name' => $name]);

            DB::table('patrol_type_categories')->where('ptc_patrol_type_id', $patrolType->pt_id)->delete();

            $rows = array_map(fn ($category) => [
                'ptc_patrol_type_id' => $patrolType->pt_id,
                'ptc_category' => $category,
            ], $categories);

            DB::table('patrol_type_categories')->insert($rows);

            $this->command?->info("Seeded patrol type \"{$name}\" for: ".implode(', ', $categories));
        }
    }
}
