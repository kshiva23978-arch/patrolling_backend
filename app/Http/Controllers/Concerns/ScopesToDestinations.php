<?php

namespace App\Http\Controllers\Concerns;


use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ScopesToDestinations
{
    protected function accessibleDestinationIds(Request $request): ?array
    {
        $user = $request->user();
        return $user instanceof Admin ? $user->accessibleDestinationIds(): null;
    }


    protected function isUnrestrictedAdmin(Request $request): bool
    {
        return $this->accessibleDestinationIds($request) === null;
    }



    protected function assetDestinationAccessible(Request $request, ?string $destinationId): void
    {
        if($destinationId === null)
            {
                return;
            }

            $ids = $this->accessibleDestinationIds($request);

            if($ids !== null && ! in_array($destinationId, $ids, true)) {
                abort(403, 'you do not have access to this destination.');
            }
    }

    protected function scopeToAccessibleRanges(Builder $query,Request $request, string $column): Builder
    {
        $ids = $this->accessibleDestinationIds($request);

             if($ids === null)
            {
                return $query;
            }

            return $query->whereIn($column, $ids);
    }
}