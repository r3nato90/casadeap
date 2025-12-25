<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\GamesKey;
use App\Models\Game;
use App\Models\User;
use App\Models\Order;
use App\Models\Wallet;
use App\Helpers\Core as Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlayFiverGatewayController extends Controller
{
    protected static string $secretPlayFiver;
    protected static string $codePlayFiver;
    protected static string $tokenPlayFiver;
    protected static string $baseUrl = 'https://api.playfivers.com';

    /**
     * Carrega credenciais do PlayFivers
     */
    private static function loadCredentials()
    {
        $setting = GamesKey::first();
        if ($setting) {
            self::$secretPlayFiver = $setting->playfiver_secret ?? '';
            self::$codePlayFiver = $setting->playfiver_code ?? '';
            self::$tokenPlayFiver = $setting->playfiver_token ?? '';
        }
    }

    /**
     * Listar provedores de jogos
     */
    public function listProviders()
    {
        self::loadCredentials();
        
        try {
            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->get(self::$baseUrl . '/api/v2/providers');

            if ($response->successful()) {
                $data = $response->json();
                
                // Salvar/atualizar provedores no banco
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $provider) {
                        \App\Models\Provider::updateOrCreate(
                            ['name' => $provider['name']],
                            [
                                'image_url' => $provider['image_url'] ?? null,
                                'status' => $provider['status'] ?? 1,
                                'wallet_name' => $provider['wallet']['name'] ?? null
                            ]
                        );
                    }
                }
                
                return response()->json([
                    'status' => true,
                    'data' => $data['data'] ?? []
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao buscar provedores',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro PlayFivers listProviders: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Listar jogos por provedor
     */
    public function listGames(Request $request)
    {
        self::loadCredentials();
        
        try {
            $params = [];
            if ($request->has('provider')) {
                $params['provider'] = $request->provider;
            }

            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->get(self::$baseUrl . '/api/v2/games', $params);

            if ($response->successful()) {
                $data = $response->json();
                
                // Salvar/atualizar jogos no banco
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $gameData) {
                        $provider = \App\Models\Provider::where('name', $gameData['provider']['name'])->first();
                        
                        \App\Models\Game::updateOrCreate(
                            ['game_code' => $gameData['game_code']],
                            [
                                'game_name' => $gameData['name'],
                                'provider_id' => $provider->id ?? null,
                                'image_url' => $gameData['image_url'] ?? null,
                                'status' => $gameData['status'] ?? 1,
                                'original' => $gameData['original'] ?? false,
                                'rounds_free' => $gameData['rounds_free'] ?? false,
                                'distribution' => 'play_fiver'
                            ]
                        );
                    }
                }
                
                return response()->json([
                    'status' => true,
                    'data' => $data['data'] ?? []
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao buscar jogos',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro PlayFivers listGames: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Iniciar jogo
     */
    public function launchGame(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'game_code' => 'required|string',
            'provider' => 'required|string',
            'lang' => 'required|string|in:pt,es,en,ja,zh,ru,th,hi',
            'user_rtp' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuário não autenticado'
                ], 401);
            }

            $game = Game::where('game_code', $request->game_code)->first();
            if (!$game) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Jogo não encontrado'
                ], 404);
            }

            $postData = [
                'agentToken' => self::$tokenPlayFiver,
                'secretKey' => self::$secretPlayFiver,
                'user_code' => $user->email,
                'game_code' => $request->game_code,
                'provider' => $request->provider,
                'game_original' => $game->original == 1,
                'user_balance' => self::getUserBalance($user->id),
                'lang' => $request->lang
            ];

            if ($request->has('user_rtp')) {
                $postData['user_rtp'] = $request->user_rtp;
            }

            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->post(self::$baseUrl . '/api/v2/game_launch', $postData);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['launch_url'])) {
                    // Criar token para o jogo
                    $token = Helper::MakeToken([
                        'id' => $user->id,
                        'game' => $request->game_code
                    ]);

                    return response()->json([
                        'status' => true,
                        'launch_url' => $data['launch_url'],
                        'user_code' => $user->email,
                        'user_balance' => $data['user_balance'] ?? $postData['user_balance'],
                        'user_created' => $data['user_created'] ?? false,
                        'name' => $data['name'] ?? $game->game_name,
                        'token' => $token
                    ]);
                }
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao iniciar jogo',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro PlayFivers launchGame: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Rodadas grátis
     */
    public function freeBonus(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'game_code' => 'required|string',
            'rounds' => 'required|integer|min:1|max:23'
        ]);

        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuário não autenticado'
                ], 401);
            }

            $postData = [
                'agent_token' => self::$tokenPlayFiver,
                'secret_key' => self::$secretPlayFiver,
                'user_code' => $user->email,
                'game_code' => $request->game_code,
                'rounds' => $request->rounds
            ];

            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->post(self::$baseUrl . '/api/v2/free_bonus', $postData);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => $data['status'] ?? true,
                    'msg' => $data['msg'] ?? 'Rodadas grátis agendadas com sucesso'
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao conceder rodadas grátis',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro PlayFivers freeBonus: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Saldo das carteiras
     */
    public function getBalances(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'agentToken' => 'required|string',
            'secretKey' => 'required|string'
        ]);

        try {
            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->get(self::$baseUrl . '/api/v2/balances', [
                'agentToken' => $request->agentToken,
                'secretKey' => $request->secretKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => true,
                    'data' => $data['data'] ?? []
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao buscar saldos',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro PlayFivers getBalances: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Informações do agente
     */
    public function getAgentInfo(Request $request)
    {
        self::loadCredentials();
        
        $request->validate([
            'agentToken' => 'required|string',
            'secretKey' => 'required|string'
        ]);

        try {
            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->get(self::$baseUrl . '/api/v2/agent', [
                'agentToken' => $request->agentToken,
                'secretKey' => $request->secretKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => true,
                    'data' => $data['data'] ?? []
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => 'Erro ao buscar informações do agente',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Erro PlayFivers getAgentInfo: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'msg' => 'Erro interno'
            ], 500);
        }
    }

    /**
     * Webhook - Saldo
     */
    public function webhookBalance(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'user_code' => 'required|string'
        ]);

        if ($request->type !== 'BALANCE') {
            return response()->json([
                'msg' => 'INVALID_TYPE',
                'balance' => 0
            ], 422);
        }

        $user = User::where('email', $request->user_code)->first();
        if (!$user) {
            return response()->json([
                'msg' => 'INVALID_USER',
                'balance' => 0
            ], 404);
        }

        $wallet = $user->wallet;
        $balance = (float) ($wallet->balance ?? 0) + 
                   (float) ($wallet->balance_bonus ?? 0) + 
                   (float) ($wallet->balance_withdrawal ?? 0);

        return response()->json([
            'msg' => '',
            'balance' => $balance
        ]);
    }

    /**
     * Webhook - Transações
     */
    public function webhookTransaction(Request $request)
    {
        self::loadCredentials();
        
        // Verificar autenticação
        $agentSecret = $request->header('agent_secret') ?? 
                      $request->header('secretKey') ?? 
                      $request->input('agent_secret') ?? 
                      $request->input('secretKey');

        $agentCode = $request->header('agent_code') ?? 
                    $request->header('agentToken') ?? 
                    $request->input('agent_code') ?? 
                    $request->input('agentToken');

        if (!hash_equals(self::$secretPlayFiver, (string) $agentSecret) ||
            (!hash_equals(self::$codePlayFiver, (string) $agentCode) && 
             !hash_equals(self::$tokenPlayFiver, (string) $agentCode))) {
            
            Log::warning('PlayFivers webhook - Acesso não autorizado', [
                'ip' => $request->ip(),
                'agent_code' => $agentCode,
                'agent_secret' => $agentSecret
            ]);

            return response()->json([
                'msg' => 'UNAUTHORIZED',
                'balance' => 0
            ], 401);
        }

        $type = strtoupper($request->input('type', ''));
        
        switch ($type) {
            case 'WINBET':
                return $this->processWinBet($request);
            case 'REFUND':
                return $this->processRefund($request);
            default:
                return response()->json([
                    'msg' => 'INVALID_TYPE',
                    'balance' => 0
                ], 422);
        }
    }

    /**
     * Processar aposta ganha/perdida
     */
    private function processWinBet(Request $request)
    {
        $user = User::where('email', $request->input('user_code'))->first();
        if (!$user) {
            return response()->json([
                'msg' => 'INVALID_USER',
                'balance' => 0
            ], 404);
        }

        $slot = $request->input('slot', []);
        $wallet = $user->wallet;
        
        $bet = (float) ($slot['bet'] ?? 0);
        $win = (float) ($slot['win'] ?? 0);
        
        // Calcular saldo antes
        $balanceBefore = (float) ($wallet->balance ?? 0) + 
                        (float) ($wallet->balance_bonus ?? 0) + 
                        (float) ($wallet->balance_withdrawal ?? 0);
        
        // Verificar saldo suficiente
        if ($balanceBefore < $bet) {
            return response()->json([
                'msg' => 'INSUFFICIENT_USER_FUNDS',
                'balance' => 0
            ], 400);
        }

        // Processar débito
        $changeBonus = null;
        if ($wallet->balance_bonus >= $bet) {
            $wallet->decrement('balance_bonus', $bet);
            $changeBonus = 'balance_bonus';
        } elseif ($wallet->balance >= $bet) {
            $wallet->decrement('balance', $bet);
            $changeBonus = 'balance';
        } elseif ($wallet->balance_withdrawal >= $bet) {
            $wallet->decrement('balance_withdrawal', $bet);
            $changeBonus = 'balance_withdrawal';
        }

        // Processar crédito se houver ganho
        if ($win > 0) {
            $wallet->increment('balance_withdrawal', $win);
        }

        // Criar registro de ordem
        if ($bet > 0 || $win > 0) {
            Order::create([
                'user_id' => $user->id,
                'session_id' => $slot['round_id'] ?? '',
                'transaction_id' => $slot['txn_id'] ?? '',
                'game' => $slot['game_code'] ?? '',
                'game_uuid' => $slot['game_code'] ?? '',
                'type' => $win > 0 ? 'win' : 'bet',
                'type_money' => $changeBonus ?? 'balance',
                'amount' => $win > 0 ? $win : $bet,
                'providers' => 'play_fiver',
                'refunded' => false,
                'round_id' => $slot['round_id'] ?? '',
                'status' => true
            ]);

            Helper::generateGameHistory(
                $user->id, 
                $win > 0 ? 'win' : 'bet', 
                $win, 
                $bet, 
                $changeBonus ?? 'balance', 
                $slot['txn_id'] ?? ''
            );
        }

        // Calcular saldo final
        $finalBalance = (float) ($wallet->balance ?? 0) + 
                       (float) ($wallet->balance_bonus ?? 0) + 
                       (float) ($wallet->balance_withdrawal ?? 0);

        return response()->json([
            'msg' => '',
            'balance' => $finalBalance
        ]);
    }

    /**
     * Processar reembolso
     */
    private function processRefund(Request $request)
    {
        $user = User::where('email', $request->input('user_code'))->first();
        if (!$user) {
            return response()->json([
                'msg' => 'INVALID_USER',
                'balance' => 0
            ], 404);
        }

        $slot = $request->input('slot', []);
        $order = Order::where('round_id', $slot['round_id'] ?? '')->first();
        
        if (!$order) {
            return response()->json([
                'msg' => 'INVALID_USER',
                'balance' => 0
            ], 404);
        }

        // Marcar como reembolsado
        $order->update(['refunded' => true]);

        // Devolver valor
        $wallet = $user->wallet;
        $wallet->increment('balance_withdrawal', $slot['win'] ?? 0);

        $finalBalance = (float) ($wallet->balance ?? 0) + 
                       (float) ($wallet->balance_bonus ?? 0) + 
                       (float) ($wallet->balance_withdrawal ?? 0);

        return response()->json([
            'msg' => '',
            'balance' => $finalBalance
        ]);
    }

    /**
     * Obter saldo do usuário
     */
    private static function getUserBalance($userId)
    {
        $user = User::find($userId);
        if (!$user) return 0;

        $wallet = $user->wallet;
        return (float) ($wallet->balance ?? 0) + 
               (float) ($wallet->balance_bonus ?? 0) + 
               (float) ($wallet->balance_withdrawal ?? 0);
    }
}
