<?php

namespace App\Http\Requests;

use App\Models\PairingCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class RedeemPairingCodeRequest extends FormRequest
{
    /**
     * Short enough that many distinct codes share a bucket, so the limiter
     * cannot be used as an oracle for whether a prefix is in use.
     */
    private const PREFIX_LENGTH = 4;

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:8', 'max:64'],
            'device_name' => ['required', 'string', 'max:120'],
        ];
    }

    public function code(): string
    {
        return mb_strtoupper(trim($this->string('code')->toString()));
    }

    /**
     * Rate limits are keyed on a hash of the code's leading characters —
     * enough to bucket repeated attacks on one code, never enough to
     * reconstruct it, and never written out in the clear — and deliberately
     * NOT on the client IP, which this app never reads or stores.
     *
     * Static because the throttle middleware runs before the FormRequest is
     * resolved and is handed the base Request.
     */
    public static function rateLimitKeyFor(Request $request): string
    {
        $prefix = substr(mb_strtoupper(trim((string) $request->input('code'))), 0, self::PREFIX_LENGTH);

        return 'pair:'.substr(hash('sha256', $prefix), 0, 32);
    }

    public function pairingCode(): ?PairingCode
    {
        return PairingCode::findRedeemable($this->code());
    }
}
