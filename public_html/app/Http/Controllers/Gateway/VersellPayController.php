<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Traits\Gateways\SuitpayTrait;
use Illuminate\Http\Request;

class VersellPayController extends Controller
{
    use SuitpayTrait;

    /**
     * Callback/Webhook genérico para confirmar depósitos VersellPay.
     */
    public function callback(Request $request)
    {
        $paymentId = $request->input('idTransaction')
            ?? $request->input('payment_id')
            ?? $request->input('transaction_id')
            ?? $request->input('id');

        $reference = $request->query('id');

        if (empty($paymentId)) {
            return response()->json(['status' => 'INVALID_REQUEST'], 422);
        }

        if (self::finalizePayment($paymentId, $reference)) {
            return response()->json(['status' => 'PAID'], 200);
        }

        return response()->json(['status' => 'PENDING'], 200);
    }
}
