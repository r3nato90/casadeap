<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Traits\Gateways\SuitpayTrait;
use Illuminate\Http\Request;

class WishPagController extends Controller
{
    use SuitpayTrait;

    /**
     * Callback/Webhook genérico para confirmar depósitos WishPag.
     * A WishPag pode enviar o id da transação em formatos diferentes, então tentamos capturar por:
     * - payment_id / idTransaction / transaction_id / id
     * - ou pelo parâmetro ?id= (reference) que geramos ao criar o PIX.
     */
    public function callback(Request $request)
    {
        $paymentId = $request->input('payment_id')
            ?? $request->input('idTransaction')
            ?? $request->input('transaction_id')
            ?? $request->input('id');

        $reference = $request->query('id');

        if (empty($paymentId) && !empty($reference)) {
            $tx = Transaction::where('reference', $reference)->first();
            $paymentId = $tx?->payment_id;
        }

        if (empty($paymentId)) {
            return response()->json(['status' => 'INVALID_REQUEST'], 422);
        }

        // finalizePayment cuida de:
        // - creditar saldo
        // - bônus/rollover
        // - CPA/afiliados
        // - status de Transaction/Deposit
        if (self::finalizePayment($paymentId, $reference)) {
            return response()->json(['status' => 'PAID'], 200);
        }

        return response()->json(['status' => 'PENDING'], 200);
    }
}
