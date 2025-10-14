<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucheFormRequest;
use App\Http\Resources\VoucheResource;
use App\Models\Vouche;
use App\Models\Supplier;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VoucheController extends Controller
{

    public function index(Request $request, $supplier_id = null)
    {
        if ($supplier_id && !Supplier::find($supplier_id)) {
            return $this->notFoundResponse("Supplier with ID {$supplier_id} not found.");
        }

        $vouches = Vouche::with(['vouchedBy', 'vouchedFor'])
            ->when(
                $supplier_id,
                function ($query, $supplier_id) {
                    return $query->where(function ($q) use ($supplier_id) {
                        $q->where('vouched_for_id', $supplier_id);
                    });
                }
            )
            ->latest()
            ->paginate(
                $this->getPaginationLimit($request)
            );

        $message = $supplier_id
            ? "Vouches for supplier {$supplier_id} retrieved successfully"
            : 'Vouches retrieved successfully';

        return $this->listResponse(
            VoucheResource::collection($vouches),
            $message,
            $this->getPaginationMeta($vouches)
        );
    }

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
