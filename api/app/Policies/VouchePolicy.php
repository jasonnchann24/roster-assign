<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\Vouche;

class VouchePolicy
{
    /**
     * Determine whether the user can delete the vouche.
     */
    public function delete(Supplier $user, Vouche $vouche): bool
    {
        return $vouche->vouched_by_id === $user->id;
    }
}
