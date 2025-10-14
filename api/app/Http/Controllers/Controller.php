<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

abstract class Controller
{
    use ApiResponseTrait;

    /**
     * Get pagination limit from request with validation
     *
     * @param Request $request
     * @param int $default Default limit (default: 15)
     * @param int $max Maximum allowed limit (default: 100)
     * @return int
     */
    protected function getPaginationLimit(Request $request, int $default = 15, int $max = 100): int
    {
        $limit = $request->get('limit', $default);
        return min(max((int) $limit, 1), $max);
    }
}
