<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucheFormRequest;
use App\Http\Resources\VoucheResource;
use App\Models\Vouche;
use App\Models\Supplier;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VoucheController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VoucheFormRequest $request)
    {
        $validated = $request->validated();

        $vouche = DB::transaction(function () use ($validated) {
            $vouche = Vouche::create($validated);

            // Increment the total_vouches counter for the supplier being vouched for
            Supplier::where('id', $validated['vouched_for_id'])
                ->increment('total_vouches');

            return $vouche;
        }, 3);

        return $this->createdResponse(
            new VoucheResource($vouche),
            "Vouche created successfully"
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Vouche $vouche)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vouche $vouche)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vouche $vouche)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vouche $vouch)
    {
        Gate::authorize('delete', $vouch);

        DB::transaction(function () use ($vouch) {
            // Decrement the total_vouches counter for the supplier
            Supplier::where('id', $vouch->vouched_for_id)
                ->decrement('total_vouches');

            // Delete the vouche
            $vouch->delete();
        }, 3);

        return $this->successResponse(
            null,
            'Vouche deleted successfully'
        );
    }
}
