<?php

namespace App\Contexts\RealEstate\Support;

use App\Contexts\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Collection;

/**
 * Les biens d'un utilisateur avec tout ce dont les calculs dérivés ont besoin. Trois actions
 * chargeaient les mêmes relations : une divergence entre elles se paierait en N+1 silencieux.
 */
class UserProperties
{
    /** @return Collection<int, Property> */
    public function forUser(int $userId): Collection
    {
        return Property::query()
            ->where('user_id', $userId)
            ->with(['leases.exceptions', 'loans', 'expenses', 'valuations'])
            ->orderBy('name')
            ->get();
    }
}
