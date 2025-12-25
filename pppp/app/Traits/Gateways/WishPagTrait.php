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

trait WishPagTrait
{
    protected static string $wishpagBaseUrl = 'https://api.gateway.wishpag.com/functions/v1';
    protected static ?string $wishpagSecretKey = null;
    protected static ?string $wishpagCompanyId = null;

    private static function generateWishPagCredentials(): void
    {
        $gateway = Gateway::first();
        self::$wishpagSecretKey = $gateway?->wishpag_secret_key;
        self::$wishpagCompanyId = $gateway?->wishpag_company_id;

        if (!empty($gateway?->wishpag_base_url)) {
            self::$wishpagBaseUrl = rtrim($gateway->wishpag_base_url, '/');
        }
    }

    private static function wishPagAuthHeader(): array
    {
        $credentials = base64_encode((string) self::$wishpagSecretKey . ':' . (string) self::$wishpagCompanyId);

        return [
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Gera PIX (QR Code / Copia e Cola) pela WishPag.
     */
    public function requestQrcodeWishPag($request): array
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

            self::generateWishPagCredentials();

            if (empty(self::$wishpagSecretKey) || empty(self::$wishpagCompanyId)) {
                return ['status' => false, 'error' => 'WishPag não configurada no Admin (Secret Key / Company ID).'];
            }

            $amount = \Helper::amountPrepare($request->amount);
            $idUnico = uniqid('wish_', true);

            $payload = [
                // Observação: a documentação disponibilizada no TXT não detalha 100% do body.
                // Enviamos os campos comuns e toleramos respostas variadas.
                'amount'      => $amount,
                'user_code'   => auth('api')->user()->email,
                'name'        => auth('api')->user()->name,
                'cpf'         => \Helper::soNumero($request->cpf),
                'email'       => auth('api')->user()->email,
                'callbackUrl' => url('/wishpag/callback', [], true) . '?id=' . urlencode($idUnico),
            ];

            $response = Http::withHeaders(self::wishPagAuthHeader())
                ->post(self::$wishpagBaseUrl . '/transactions', $payload);

            if (!$response->successful()) {
                return [
                    'status' => false,
                    'error'  => $response->body(),
                ];
            }

            $data = $response->json() ?? [];

            // Tentativa de detectar o campo de "copia e cola" em diferentes formatos.
            $qrcode =
                $data['paymentCode'] ??
                $data['payment_code'] ??
                ($data['pix']['code'] ?? null) ??
                ($data['pix']['copy_paste'] ?? null) ??
                $data['pix_copy_paste'] ??
                $data['qrcode'] ??
                $data['qr_code'] ??
                $data['code'] ??
                null;

            $idTransaction =
                $data['idTransaction'] ??
                $data['transaction_id'] ??
                $data['id'] ??
                null;

            // Persistência (mesmo padrão dos outros gateways)
            if (!empty($idTransaction)) {
                self::generateWishPagTransaction((string) $idTransaction, $amount, $idUnico);
                self::generateWishPagDeposit((string) $idTransaction, $amount);
            } else {
                // fallback: persistimos usando o idUnico
                self::generateWishPagTransaction($idUnico, $amount, $idUnico);
                self::generateWishPagDeposit($idUnico, $amount);
            }

            return [
                'status'        => true,
                'idTransaction' => $idTransaction ?? $idUnico,
                'qrcode'        => $qrcode,
                'raw'           => $data,
            ];
        } catch (Exception $e) {
            Log::error('WishPag error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ['status' => false, 'error' => 'Erro interno ao gerar PIX WishPag.'];
        }
    }

    private static function generateWishPagTransaction(string $idTransaction, float $amount, string $idUnico): void
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

    private static function generateWishPagDeposit(string $idTransaction, float $amount): void
    {
        Deposit::create([
            'payment_id' => $idTransaction,
            'user_id'    => auth('api')->id(),
            'amount'     => $amount,
            'status'     => 0,
        ]);
    }
}
