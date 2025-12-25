<?php

namespace App\Traits\Gateways;

use App\Models\Deposit;
use App\Models\Gateway;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

trait VersellPayTrait
{
    protected static string $versellBaseUrl = 'https://api.versellpay.com/api/v1';
    protected static ?string $versellVSPI = null;
    protected static ?string $versellVSPS = null;

    private static function generateVersellCredentials(): void
    {
        $gateway = Gateway::first();
        self::$versellVSPI = $gateway?->versell_vspi;
        self::$versellVSPS = $gateway?->versell_vsps;

        if (!empty($gateway?->versell_base_url)) {
            self::$versellBaseUrl = rtrim($gateway->versell_base_url, '/');
        }
    }

    private static function versellHeaders(): array
    {
        return [
            'vspi'         => (string) self::$versellVSPI,
            'vsps'         => (string) self::$versellVSPS,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    public function requestQrcodeVersellPay($request): array
    {
        try {
            $setting = \Helper::getSetting();

            $rules = [
                'amount' => ['required', 'numeric', 'min:' . $setting->min_deposit, 'max:' . $setting->max_deposit],
                'cpf'    => ['required', 'string', 'max:255'],
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ['status' => false, 'errors' => $validator->errors()];
            }

            self::generateVersellCredentials();

            if (empty(self::$versellVSPI) || empty(self::$versellVSPS)) {
                return ['status' => false, 'error' => 'VersellPay não configurada no Admin (VSPI / VSPS).'];
            }

            $amount = \Helper::amountPrepare($request->amount);
            $idUnico = uniqid('versell_', true);

            $payload = [
                'requestNumber'    => time(),
                'dueDate'          => Carbon::now()->addDay()->toDateTimeString(),
                'amount'           => $amount,
                'shippingAmount'   => 0.0,
                'usernameCheckout' => 'checkout',
                'callbackUrl'      => url('/versellpay/callback', [], true) . '?id=' . urlencode($idUnico),
                'client'           => [
                    'name'     => auth('api')->user()->name,
                    'document' => \Helper::soNumero($request->cpf),
                    'email'    => auth('api')->user()->email,
                ],
            ];

            $response = Http::withHeaders(self::versellHeaders())
                ->post(self::$versellBaseUrl . '/gateway/request-qrcode', $payload);

            if (!$response->successful()) {
                return ['status' => false, 'error' => $response->body()];
            }

            $data = $response->json() ?? [];

            $idTransaction = $data['idTransaction'] ?? ($data['id'] ?? null);
            $qrcode = $data['paymentCode'] ?? ($data['qrcode'] ?? null);

            if (!empty($idTransaction)) {
                self::generateVersellTransaction((string) $idTransaction, $amount, $idUnico);
                self::generateVersellDeposit((string) $idTransaction, $amount);
            } else {
                self::generateVersellTransaction($idUnico, $amount, $idUnico);
                self::generateVersellDeposit($idUnico, $amount);
            }

            return [
                'status'        => true,
                'idTransaction' => $idTransaction ?? $idUnico,
                'qrcode'        => $qrcode,
                'raw'           => $data,
            ];
        } catch (Exception $e) {
            Log::error('VersellPay error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ['status' => false, 'error' => 'Erro interno ao gerar PIX VersellPay.'];
        }
    }

    private static function generateVersellTransaction(string $idTransaction, float $amount, string $idUnico): void
    {
        Transaction::create([
            'payment_id' => $idTransaction,
            'user_id'    => auth('api')->id(),
            'type'       => 'deposit',
            'amount'     => $amount,
            'status'     => 0,
            'reference'  => $idUnico,
        ]);
    }

    private static function generateVersellDeposit(string $idTransaction, float $amount): void
    {
        Deposit::create([
            'payment_id' => $idTransaction,
            'user_id'    => auth('api')->id(),
            'amount'     => $amount,
            'status'     => 0,
        ]);
    }
}
