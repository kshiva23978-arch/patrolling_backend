<?php

namespace Database\Seeders;

use App\Models\Beach;
use App\Models\Destination;
use Illuminate\Database\Seeder;

/**
 * A starter set of destinations and beaches so the admin panel/app aren't
 * empty on a fresh environment — edit or delete these freely from the admin
 * panel (Destinations/Beaches) afterward; nothing here is load-bearing.
 */
class DestinationBeachSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'Goa Coast' => ['Baga Beach', 'Calangute Beach', 'Palolem Beach'],
            'Mumbai Coast' => ['Juhu Beach', 'Versova Beach', 'Marine Drive'],
            'Chennai Coast' => ['Marina Beach', 'Elliot\'s Beach'],
            'Kerala Coast' => ['Kovalam Beach', 'Varkala Beach'],
        ];

        foreach ($definitions as $destinationName => $beachNames) {
            $destination = Destination::firstOrCreate(['ds_name' => $destinationName]);

            foreach ($beachNames as $beachName) {
                Beach::firstOrCreate([
                    'bc_destination_id' => $destination->ds_id,
                    'bc_name' => $beachName,
                ]);
            }

            $this->command?->info("Seeded destination \"{$destinationName}\" with ".count($beachNames).' beaches.');
        }
    }
}
