<?php

namespace App\Http\Controllers;

use App\Enums\AccessRequestStatus;
use App\Http\Requests\StoreAccessRequestRequest;
use App\Models\AccessRequest;
use Illuminate\Http\JsonResponse;

class AccessRequestController extends Controller
{
    /**
     * Always answers "pending" whatever the stored state, so the endpoint can
     * never be used to probe which addresses are already approved or denied.
     */
    public function store(StoreAccessRequestRequest $request): JsonResponse
    {
        AccessRequest::firstOrCreate(
            ['email' => $request->normalisedEmail()],
            ['name' => $request->input('name'), 'status' => AccessRequestStatus::Pending],
        );

        return response()->json([
            'status' => AccessRequestStatus::Pending->value,
            'message' => 'Access request received. You will hear back by email.',
        ], 201);
    }
}
