<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Gateway as GatewayModel;
use App\Models\Setting;
use App\Models\AffiliateHistory;
use App\Models\AffiliateLogs;
use App\Models\ConfigRoundsFree;
use App\Services\PlayFiverService;
use App\Helpers\Core as Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VersellPayGatewayController extends Controller
{
    protected static string $baseUrl = 'https://api.versellpay.com/api/v1';
    protected static string $clientId;
    protected static string $clientSecret;

    /**
     * Carregar credenciais
     */
    private static function loadCredentials()
    {
        $gateway = GatewayModel::where('name', 'versellpay')->first();
        if ($gateway) {
            self::$clientId = $gateway->client_id ?? '';
            self::$clientSecret = $gateway->client_secret ?? '';
        }
    }

    /**
     * Gerar PIX (Cash-in)
     */
    public function generatePix(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'requestNumber' => 'required|string',
            'client.name' => 'required|string',
            'client.document' => 'required|string',
            'client.email' => 'required|email',
            'client.phoneNumber' => 'required|string',
            'client.address.street' => 'required|string',
            'client.address.number' => 'required|string',
            'client.address.neighborhood' => 'required|string',
            'client.address.city' => 'required|string',
            'client.address.state' => 'required|string',
            'client.address.zipCode' => 'required|string',
            'client.address.codIbge' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.description' => 'required|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.value' => 'required|numeric|min:0.01',
            'callbackUrl' => 'nullable|url',
            'split' => 'nullable|array',
            'split.account' => 'nullable|string',
            'split.percentageSplit' => 'nullable|numeric|min:0|max:100'
        ]);

        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuário não autenticado'
                ], 401);
            }

            // Preparar payload
            $payload = [
                'requestNumber' => $request->requestNumber,
                'amount' => (float) $request->amount,
                'client' => [
                    'name' => $request->input('client.name'),
                    'document' => Helper::soNumero($request->input('client.document')),
                    'phoneNumber' => Helper::soNumero($request->input('client.phoneNumber')),
                    'email' => $request->input('client.email'),
                    'address' => [
                        'street' => $request->input('client.address.street'),
                        'number' => $request->input('client.address.number'),
                        'complement' => $request->input('client.address.complement') ?? '',
                        'zipCode' => Helper::soNumero($request->input('client.address.zipCode')),
                        'neighborhood' => $request->input('client.address.neighborhood'),
                        'city' => $request->input('client.address.city'),
                        'state' => $request->input('client.address.state'),
                        'codIbge' => $request->input('client.address.codIbge') ?? ''
                    ]
                ],
                'products' => array_map(function($product) {
                    return [
                        'description' => $product['description'],
                        'quantity' => (int) $product['quantity'],
                        'value' => (float) $product['value']
                    ];
                }, $request->products)
            ];

            // Adicionar callbackUrl se fornecido
            if ($request->has('callbackUrl')) {
                $payload['callbackUrl'] = $request->callbackUrl;
            } else {
                $payload['callbackUrl'] = url('/versellpay/callback');
            }

            // Adicionar split se fornecido
            if ($request->has('split')) {
                $payload['split'] = [
                    'account' => $request->input('split.account'),
                    'percentageSplit' => (float) $request->input('split.percentageSplit')
                ];
            }

            $response = Http::withHeaders([
                'vspi' => self::$clientId,
                'vsps' => self::$clientSecret,
                'Content-Type' => 'application/json'
            ])->post(self::$baseUrl . '/gateway/request-qrcode', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Salvar transação no banco
                $transaction = Transaction::create([
                    'payment_id' => $data['idTransaction'] ?? null,
                    'user_id' => $user->id,
                    'payment_method' => 'PIX',
                    'price' => $request->amount,
                    'currency' => 'BRL',
                    'status' => 0,
                    'reference' => $data['idTransaction'] ?? uniqid(),
                    'metadata' => json_encode($payload)
                ]);

                // Salvar deposito
                Deposit::create([
                    'payment_id' => $data['idTransaction'] ?? null,
                    'user_id' => $user->id,
                    'amount' => $request->amount,
                    'type' => 'pix',
                    'currency' => 'BRL',
                    'symbol' => 'R$',
                    'status' => 0
                ]);

                return response()->json([
                    'status' => true,
                    'idTransaction' => $data['idTransaction'],
                    'paymentCode' => $data['paymentCode'] ?? null,
                    'paymentCodeBase64' => $data['paymentCodeBase64'] ?? null,
                    'response' => $data['response'] ?? 'OK'
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao gerar PIX',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro VersellPay generatePix: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Transferência PIX (Cash-out)
     */
    public function pixTransfer(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'key' => 'required|string',
            'typeKey' => 'required|string|in:CPF,CNPJ,EMAIL,PHONE',
            'document' => 'required|string',
            'automaticWithdraw' => 'nullable|boolean',
            'callbackUrl' => 'nullable|url'
        ]);

        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuário não autenticado'
                ], 401);
            }

            // Verificar saldo
            $wallet = $user->wallet;
            $balance = (float) ($wallet->balance ?? 0) + 
                      (float) ($wallet->balance_bonus ?? 0) + 
                      (float) ($wallet->balance_withdrawal ?? 0);

            if ($balance < $request->amount) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Saldo insuficiente'
                ], 400);
            }

            $payload = [
                'amount' => (float) $request->amount,
                'key' => Helper::soNumero($request->key),
                'typeKey' => $request->typeKey,
                'document' => Helper::soNumero($request->document)
            ];

            if ($request->has('automaticWithdraw')) {
                $payload['automaticWithdraw'] = $request->automaticWithdraw;
            }

            if ($request->has('callbackUrl')) {
                $payload['callbackUrl'] = $request->callbackUrl;
            } else {
                $payload['callbackUrl'] = url('/versellpay/callback');
            }

            $response = Http::withHeaders([
                'vspi' => self::$clientId,
                'vsps' => self::$clientSecret,
                'Content-Type' => 'application/json'
            ])->post(self::$baseUrl . '/gateway/pix-payment', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Deduzir saldo do usuário
                if ($wallet->balance >= $request->amount) {
                    $wallet->decrement('balance', $request->amount);
                } elseif ($wallet->balance_withdrawal >= $request->amount) {
                    $wallet->decrement('balance_withdrawal', $request->amount);
                } else {
                    // Deduzir proporcionalmente
                    $remaining = $request->amount;
                    
                    if ($wallet->balance > 0) {
                        $deduct = min($wallet->balance, $remaining);
                        $wallet->decrement('balance', $deduct);
                        $remaining -= $deduct;
                    }
                    
                    if ($remaining > 0 && $wallet->balance_withdrawal > 0) {
                        $deduct = min($wallet->balance_withdrawal, $remaining);
                        $wallet->decrement('balance_withdrawal', $deduct);
                        $remaining -= $deduct;
                    }
                    
                    if ($remaining > 0 && $wallet->balance_bonus > 0) {
                        $deduct = min($wallet->balance_bonus, $remaining);
                        $wallet->decrement('balance_bonus', $deduct);
                    }
                }

                return response()->json([
                    'status' => true,
                    'statusTransaction' => $data['status'] ?? 'success',
                    'idTransaction' => $data['idTransaction'] ?? null,
                    'message' => $data['message'] ?? 'Transferência realizada com sucesso'
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao realizar transferência',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro VersellPay pixTransfer: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Consultar transação na wallet
     */
    public function consultTransaction(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'requestNumber' => 'nullable|string',
            'idTransaction' => 'nullable|string',
            'endToEnd' => 'nullable|string'
        ]);

        // Pelo menos um campo deve ser fornecido
        if (!$request->has('requestNumber') && !$request->has('idTransaction') && !$request->has('endToEnd')) {
            return response()->json([
                'status' => false,
                'msg' => 'É necessário informar pelo menos um campo: requestNumber, idTransaction ou endToEnd'
            ], 422);
        }

        try {
            $payload = [];
            if ($request->has('requestNumber')) {
                $payload['requestNumber'] = $request->requestNumber;
            }
            if ($request->has('idTransaction')) {
                $payload['idTransaction'] = $request->idTransaction;
            }
            if ($request->has('endToEnd')) {
                $payload['endToEnd'] = $request->endToEnd;
            }

            $response = Http::withHeaders([
                'vspi' => self::$clientId,
                'vsps' => self::$clientSecret,
                'Content-Type' => 'application/json'
            ])->post(self::$baseUrl . '/gateway/walletTransaction', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => true,
                    'transaction' => $data['transaction'] ?? null
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao consultar transação',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro VersellPay consultTransaction: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Consultar saldo (Wallet)
     */
    public function getBalance(Request $request)
    {
        self::loadCredentials();
        
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuário não autenticado'
                ], 401);
            }

            // Verificar se há transações pendentes
            $pendingTransactions = Transaction::where('user_id', $user->id)
                ->where('status', 0)
                ->where('payment_method', 'PIX')
                ->get();

            $balance = (float) ($user->wallet->balance ?? 0) + 
                      (float) ($user->wallet->balance_bonus ?? 0) + 
                      (float) ($user->wallet->balance_withdrawal ?? 0);

            return response()->json([
                'status' => true,
                'balance' => $balance,
                'pending_transactions' => $pendingTransactions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Erro VersellPay getBalance: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Callback/Webhook para notificações
     */
    public function callback(Request $request)
    {
        try {
            $idTransaction = $request->input('idTransaction');
            $statusTransaction = $request->input('statusTransaction');
            $typeTransaction = $request->input('typeTransaction');

            if (empty($idTransaction)) {
                return response()->json(['status' => 'INVALID_REQUEST'], 422);
            }

            // Processar baseado no tipo de transação
            switch ($typeTransaction) {
                case 'PIX':
                    return $this->processPixCashIn($request);
                case 'PIX_REFUND':
                    return $this->processPixRefund($request);
                default:
                    return $this->processGenericTransaction($request);
            }

        } catch (\Exception $e) {
            Log::error('Erro VersellPay callback: ' . $e->getMessage());
            return response()->json(['status' => 'ERROR'], 500);
        }
    }

    /**
     * Processar PIX Cash-in
     */
    private function processPixCashIn(Request $request)
    {
        $idTransaction = $request->input('idTransaction');
        $statusTransaction = $request->input('statusTransaction');
        $value = $request->input('value');
        $debtorName = $request->input('debtorName');
        $debtorDocument = $request->input('debtorDocument');
        $endToEnd = $request->input('endToEnd');

        if ($statusTransaction === 'PAID_OUT') {
            // Transação paga - creditar valor
            $transaction = Transaction::where('payment_id', $idTransaction)
                ->where('status', 0)
                ->first();

            if ($transaction) {
                $this->finalizePayment($idTransaction);
            }

            return response()->json(['status' => 'OK'], 200);
        }

        return response()->json(['status' => 'PENDING'], 200);
    }

    /**
     * Processar PIX Refund
     */
    private function processPixRefund(Request $request)
    {
        $idTransaction = $request->input('idTransaction');
        $statusTransaction = $request->input('statusTransaction');
        $value = $request->input('value');

        if ($statusTransaction === 'PAID_OUT') {
            // Reembolso processado
            Log::info('VersellPay PIX Refund processado', [
                'idTransaction' => $idTransaction,
                'value' => $value
            ]);
        }

        return response()->json(['status' => 'OK'], 200);
    }

    /**
     * Processar transação genérica
     */
    private function processGenericTransaction(Request $request)
    {
        $idTransaction = $request->input('idTransaction');
        $statusTransaction = $request->input('statusTransaction');

        switch ($statusTransaction) {
            case 'PAID_OUT':
                $this->finalizePayment($idTransaction);
                break;
            case 'NOT_PAID':
                Log::warning('VersellPay transação não paga', [
                    'idTransaction' => $idTransaction,
                    'message' => $request->input('message')
                ]);
                break;
            case 'INSUFFICIENT_BALANCE':
                Log::warning('VersellPay saldo insuficiente', [
                    'idTransaction' => $idTransaction
                ]);
                break;
            case 'LIQUIDATED':
                Log::info('VersellPay transação já liquidada', [
                    'idTransaction' => $idTransaction
                ]);
                break;
        }

        return response()->json(['status' => 'OK'], 200);
    }

    /**
     * Finalizar pagamento (método interno)
     */
    private function finalizePayment($idTransaction): bool
    {
        $transaction = Transaction::where('payment_id', $idTransaction)
            ->where('status', 0)
            ->first();

        if (!$transaction) {
            return false;
        }

        $user = User::find($transaction->user_id);
        if (!$user) {
            return false;
        }

        $wallet = Wallet::where('user_id', $transaction->user_id)->first();
        if (!$wallet) {
            return false;
        }

        $setting = Setting::first();
        
        // Verificar se é primeiro depósito
        $checkTransactions = Transaction::where('user_id', $transaction->user_id)
            ->where('status', 1)
            ->count();

        if ($checkTransactions == 0) {
            // Pagar bônus de primeiro depósito
            $bonus = Helper::porcentagem_xn($setting->initial_bonus ?? 0, $transaction->price);
            $wallet->increment('balance_bonus', $bonus);
            $wallet->update(['balance_bonus_rollover' => $bonus * ($setting->rollover ?? 1)]);
        }

        // Rodadas grátis
        $configRounds = ConfigRoundsFree::orderBy('value', 'asc')->get();
        foreach ($configRounds as $value) {
            if ($transaction->price >= $value->value) {
                $dados = [
                    'username' => $user->email,
                    'game_code' => $value->game_code,
                    'rounds' => $value->spins
                ];
                PlayFiverService::RoundsFree($dados);
                break;
            }
        }

        // Rollover do depósito
        $wallet->update([
            'balance_deposit_rollover' => $transaction->price * intval($setting->rollover_deposit ?? 1)
        ]);

        // Creditar saldo
        if ($wallet->increment('balance', $transaction->price)) {
            // Atualizar status da transação
            if ($transaction->update(['status' => 1])) {
                $deposit = Deposit::where('payment_id', $idTransaction)->first();
                if ($deposit) {
                    // Processar CPA/Afiliados
                    $this->processAffiliateCPA($user, $transaction->price);

                    // Atualizar status do depósito
                    if ($deposit->update(['status' => 1])) {
                        // Notificar admins
                        $admins = User::where('role_id', 0)->get();
                        foreach ($admins as $admin) {
                            $admin->notify(new \App\Notifications\NewDepositNotification(
                                $user->name, 
                                $transaction->price
                            ));
                        }
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Processar comissão de afiliado CPA
     */
    private function processAffiliateCPA($user, $depositAmount)
    {
        $affHistoryCPA = AffiliateHistory::where('user_id', $user->id)
            ->where('commission_type', 'cpa')
            ->where('status', 0)
            ->first();

        if (!$affHistoryCPA) {
            return;
        }

        $sponsor = User::find($user->inviter);
        if (!$sponsor) {
            return;
        }

        if ($affHistoryCPA->deposited_amount >= $sponsor->affiliate_baseline || 
            $depositAmount >= $sponsor->affiliate_baseline) {
            
            $walletCpa = Wallet::where('user_id', $affHistoryCPA->inviter)->first();
            if ($walletCpa) {
                // Pagar CPA
                $walletCpa->increment('refer_rewards', $sponsor->affiliate_cpa);
                
                $affHistoryCPA->update([
                    'status' => 1,
                    'deposited' => $depositAmount,
                    'commission_paid' => $sponsor->affiliate_cpa
                ]);

                AffiliateLogs::create([
                    'user_id' => $sponsor->id,
                    'commission' => $sponsor->affiliate_cpa,
                    'commission_type' => 'cpa',
                    'type' => 'increment'
                ]);
            }
        } else {
            // Atualizar valor depositado
            $affHistoryCPA->update(['deposited_amount' => $depositAmount]);
        }
    }
}
