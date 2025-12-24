<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Gateway;
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

class WishPagGatewayController extends Controller
{
    protected static string $baseUrl = 'https://api.gateway.wishpag.com/functions/v1';
    protected static string $companyId;
    protected static string $secretKey;
    protected static string $publicKey;

    /**
     * Carregar credenciais
     */
    private static function loadCredentials()
    {
        $gateway = Gateway::where('name', 'wishpag')->first();
        if ($gateway) {
            self::$companyId = $gateway->company_id ?? '';
            self::$secretKey = $gateway->secret_key ?? '';
            self::$publicKey = $gateway->public_key ?? '';
        }
    }

    /**
     * Criar pagamento (PIX, Cartão, Boleto)
     */
    public function createPayment(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'paymentMethod' => 'required|string|in:PIX,CARD,BOLETO',
            'amount' => 'required|numeric|min:1',
            'customer.name' => 'required|string',
            'customer.document' => 'required|string',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string',
            'items.*.unitPrice' => 'required|numeric|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'postbackUrl' => 'nullable|url',
            'installments' => 'nullable|integer|min:1|max:12',
            'card' => 'nullable|array',
            'card.id' => 'nullable|string',
            'card.number' => 'nullable|string',
            'card.holderName' => 'nullable|string',
            'card.expMonth' => 'nullable|integer',
            'card.expYear' => 'nullable|integer',
            'card.cvv' => 'nullable|string',
            'boleto' => 'nullable|array',
            'pix' => 'nullable|array',
            'shipping' => 'nullable|array',
            'split' => 'nullable|array',
            'metadata' => 'nullable|array',
            'description' => 'nullable|string|max:500',
            'ip' => 'nullable|ip'
        ]);

        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuário não autenticado'
                ], 401);
            }

            // Preparar dados do cliente
            $customer = [
                'name' => $request->input('customer.name'),
                'document' => $request->input('customer.document'),
                'email' => $request->input('customer.email'),
                'phone' => $request->input('customer.phone')
            ];

            // Preparar payload base
            $payload = [
                'amount' => (int) ($request->amount * 100), // Converter para centavos
                'paymentMethod' => $request->paymentMethod,
                'customer' => $customer,
                'items' => array_map(function($item) {
                    return [
                        'title' => $item['title'],
                        'unitPrice' => (int) ($item['unitPrice'] * 100), // Converter para centavos
                        'quantity' => (int) $item['quantity'],
                        'externalRef' => $item['externalRef'] ?? null
                    ];
                }, $request->items)
            ];

            // Adicionar campos opcionais
            if ($request->has('postbackUrl')) {
                $payload['postbackUrl'] = $request->postbackUrl;
            }

            if ($request->has('description')) {
                $payload['description'] = $request->description;
            }

            if ($request->has('ip')) {
                $payload['ip'] = $request->ip;
            }

            if ($request->has('metadata')) {
                $payload['metadata'] = $request->metadata;
            }

            // Adicionar método de pagamento específico
            if ($request->paymentMethod === 'CARD') {
                if ($request->has('installments')) {
                    $payload['installments'] = (int) $request->installments;
                }
                
                if ($request->has('card')) {
                    $card = $request->card;
                    
                    // Se tiver ID do token, usar token
                    if (isset($card['id'])) {
                        $payload['card'] = ['id' => $card['id']];
                    } else {
                        // Senão, usar dados completos do cartão
                        $payload['card'] = [
                            'number' => $card['number'],
                            'holderName' => $card['holderName'],
                            'expMonth' => (int) $card['expMonth'],
                            'expYear' => (int) $card['expYear'],
                            'cvv' => $card['cvv']
                        ];
                    }
                }
            } elseif ($request->paymentMethod === 'BOLETO') {
                if ($request->has('boleto')) {
                    $payload['boleto'] = $request->boleto;
                }
            } elseif ($request->paymentMethod === 'PIX') {
                if ($request->has('pix')) {
                    $payload['pix'] = $request->pix;
                }
            }

            // Adicionar shipping se fornecido
            if ($request->has('shipping')) {
                $payload['shipping'] = $request->shipping;
            }

            // Adicionar split se fornecido
            if ($request->has('split')) {
                $payload['split'] = array_map(function($split) {
                    return [
                        'recipientId' => $split['recipientId'],
                        'percentage' => (float) $split['percentage'],
                        'netAmount' => isset($split['netAmount']) ? (int) ($split['netAmount'] * 100) : null
                    ];
                }, $request->split);
            }

            // Credenciais para autenticação Basic
            $credentials = base64_encode(self::$secretKey . ':' . self::$companyId);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ])->post(self::$baseUrl . '/transactions', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Salvar transação no banco
                $transaction = Transaction::create([
                    'payment_id' => $data['id'] ?? null,
                    'user_id' => $user->id,
                    'payment_method' => $request->paymentMethod,
                    'price' => $request->amount,
                    'currency' => 'BRL',
                    'status' => 0,
                    'reference' => $data['id'] ?? uniqid(),
                    'metadata' => json_encode($request->metadata ?? [])
                ]);

                // Salvar deposito
                Deposit::create([
                    'payment_id' => $data['id'] ?? null,
                    'user_id' => $user->id,
                    'amount' => $request->amount,
                    'type' => strtolower($request->paymentMethod),
                    'currency' => 'BRL',
                    'symbol' => 'R$',
                    'status' => 0
                ]);

                // Preparar resposta
                $responseData = [
                    'status' => true,
                    'transaction_id' => $data['id'],
                    'status' => $data['status'] ?? 'waiting_payment',
                    'payment_method' => $request->paymentMethod
                ];

                // Adicionar dados específicos do método
                if ($request->paymentMethod === 'PIX') {
                    $responseData['qrcode'] = $data['pix']['qrcode'] ?? null;
                    $responseData['paymentCode'] = $data['pix']['paymentCode'] ?? null;
                    $responseData['expirationDate'] = $data['pix']['expirationDate'] ?? null;
                } elseif ($request->paymentMethod === 'BOLETO') {
                    $responseData['boleto_url'] = $data['boleto']['url'] ?? null;
                    $responseData['barcode'] = $data['boleto']['barcode'] ?? null;
                } elseif ($request->paymentMethod === 'CARD') {
                    $responseData['card_status'] = $data['card']['status'] ?? null;
                }

                return response()->json($responseData);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao criar pagamento',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro WishPag createPayment: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Listar transações
     */
    public function listTransactions(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,paid,failed,refunded,expired',
            'paymentMethod' => 'nullable|string|in:PIX,CARD,BOLETO',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date'
        ]);

        try {
            $params = [];
            if ($request->has('page')) $params['page'] = $request->page;
            if ($request->has('limit')) $params['limit'] = $request->limit;
            if ($request->has('status')) $params['status'] = $request->status;
            if ($request->has('paymentMethod')) $params['paymentMethod'] = $request->paymentMethod;
            if ($request->has('startDate')) $params['startDate'] = $request->startDate;
            if ($request->has('endDate')) $params['endDate'] = $request->endDate;

            $credentials = base64_encode(self::$secretKey . ':' . self::$companyId);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ])->get(self::$baseUrl . '/transactions', $params);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => true,
                    'data' => $data
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao listar transações',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro WishPag listTransactions: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Consultar transação específica
     */
    public function getTransaction($id)
    {
        self::loadCredentials();
        
        try {
            $credentials = base64_encode(self::$secretKey . ':' . self::$companyId);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ])->get(self::$baseUrl . '/transactions/' . $id);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => true,
                    'data' => $data
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Transação não encontrada',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro WishPag getTransaction: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Estornar transação
     */
    public function refundTransaction(Request $request, $id)
    {
        self::loadCredentials();
        
        $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $payload = [];
            if ($request->has('amount')) {
                $payload['amount'] = (int) ($request->amount * 100); // Converter para centavos
            }
            if ($request->has('reason')) {
                $payload['reason'] = $request->reason;
            }

            $credentials = base64_encode(self::$secretKey . ':' . self::$companyId);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ])->delete(self::$baseUrl . '/transactions/' . $id, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Atualizar transação local
                $transaction = Transaction::where('payment_id', $id)->first();
                if ($transaction) {
                    $transaction->update(['status' => 3]); // Status de estornado
                }

                return response()->json([
                    'status' => true,
                    'data' => $data
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao estornar transação',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro WishPag refundTransaction: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Atualizar status de entrega
     */
    public function updateDeliveryStatus(Request $request, $id)
    {
        self::loadCredentials();
        
        try {
            $credentials = base64_encode(self::$secretKey . ':' . self::$companyId);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ])->put(self::$baseUrl . '/transactions/' . $id . '/delivery');

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => true,
                    'data' => $data
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao atualizar status de entrega',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro WishPag updateDeliveryStatus: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Tokenização de cartão
     */
    public function tokenizeCard(Request $request)
    {
        $request->validate([
            'number' => 'required|string',
            'holderName' => 'required|string',
            'expMonth' => 'required|integer|min:1|max:12',
            'expYear' => 'required|integer',
            'cvv' => 'required|string'
        ]);

        try {
            // Usar biblioteca JavaScript do WishPag para tokenização
            // Isso deve ser feito no frontend
            
            return response()->json([
                'status' => true,
                'msg' => 'Use a biblioteca JavaScript do WishPag para tokenização',
                'script_url' => 'https://api.gateway.wishpag.com/functions/v1/js',
                'public_key' => self::$publicKey
            ]);

        } catch (\Exception $e) {
            Log::error('Erro WishPag tokenizeCard: ' . $e->getMessage());
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
            $paymentId = $request->input('payment_id')
                ?? $request->input('idTransaction')
                ?? $request->input('transaction_id')
                ?? $request->input('id');

            $reference = $request->query('id');

            // Se não encontrar paymentId no body, tentar pela referência
            if (empty($paymentId) && !empty($reference)) {
                $transaction = Transaction::where('reference', $reference)->first();
                $paymentId = $transaction?->payment_id;
            }

            if (empty($paymentId)) {
                return response()->json(['status' => 'INVALID_REQUEST'], 422);
            }

            // Processar pagamento
            if ($this->finalizePayment($paymentId, $reference)) {
                return response()->json(['status' => 'PAID'], 200);
            }

            return response()->json(['status' => 'PENDING'], 200);

        } catch (\Exception $e) {
            Log::error('Erro WishPag callback: ' . $e->getMessage());
            return response()->json(['status' => 'ERROR'], 500);
        }
    }

    /**
     * Finalizar pagamento (método interno)
     */
    public function finalizePayment($paymentId, $reference = null): bool
    {
        $transaction = Transaction::where('payment_id', $paymentId)
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
                $deposit = Deposit::where('payment_id', $paymentId)->first();
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
