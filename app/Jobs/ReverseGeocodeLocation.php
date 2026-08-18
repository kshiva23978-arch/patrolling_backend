<?php

namespace App\Jobs;

use App\Services\GoogleGeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Resolves a lat/lng pair into an address in the background so the request that
 * captured the GPS point never blocks on Google's response time.
 */
class ReverseGeocodeLocation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly string $modelClass,
        private readonly string $primaryKeyValue,
        private readonly string $primaryKeyColumn,
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly string $addressColumn,
    ) {}

    public function handle(GoogleGeocodingService $geocoder): void
    {
        $address = $geocoder->reverseGeocode($this->latitude, $this->longitude);

        if ($address === null) {
            return;
        }

        $this->modelClass::query()
            ->where($this->primaryKeyColumn, $this->primaryKeyValue)
            ->update([$this->addressColumn => $address]);
    }
}
