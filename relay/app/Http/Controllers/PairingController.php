<?php

namespace App\Http\Controllers;

use App\Http\Requests\RedeemPairingCodeRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PairingController extends Controller
{
    /**
     * Redeems a pairing code for a device credential. The plaintext secret is
     * returned here and nowhere else — the relay only ever stores its hash.
     */
    public function store(RedeemPairingCodeRequest $request): JsonResponse
    {
        [$device, $secret] = DB::transaction(function () use ($request) {
            $code = $request->pairingCode();

            if ($code === null) {
                throw ValidationException::withMessages([
                    'code' => 'That pairing code is not valid or has expired.',
                ]);
            }

            // Claim the code inside the transaction so two concurrent
            // redemptions cannot both mint a device.
            $claimed = $code->newQuery()
                ->whereKey($code->getKey())
                ->whereNull('redeemed_at')
                ->update(['redeemed_at' => now()]);

            if ($claimed !== 1) {
                throw ValidationException::withMessages([
                    'code' => 'That pairing code is not valid or has expired.',
                ]);
            }

            return Device::register($code->user, $request->string('device_name')->toString());
        });

        return response()->json([
            'device_id' => $device->id,
            'device_secret' => $secret,
            'device_name' => $device->name,
        ]);
    }
}
