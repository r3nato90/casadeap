<?php
namespace App\Traits\Providers;

use App\Models\GamesKey;
use App\Models\Order;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as FacadesRequest;
use App\Helpers\Core as Helper;

trait PlayFiverTrait
{

    /**
     * @var string
     */
    protected static $secretPlayFiver;
    protected static $codePlayFiver;
    protected static $tokenPlayFiver;

    private static function credencialFiverPlay()
    {
        $setting = GamesKey::first();
        self::$secretPlayFiver = $setting->getAttributes()['playfiver_secret'];
        self::$codePlayFiver = $setting->getAttributes()['playfiver_code'];
        self::$tokenPlayFiver = $setting->getAttributes()['playfiver_token'];
    }

    public static function playFiverLaunch($id, $demo)
    {
        self::credencialFiverPlay();

	$game = Game::where("game_code", $id)->first();
        // Continua com o lançamento do jogo independentemente do sucesso ou falha ao obter o saldo do agente
        $postArray = [
            "agentToken" => self::$tokenPlayFiver,
            "secretKey" => self::$secretPlayFiver,
            "user_code" => Auth::guard("api")->user()->email,
            "game_code" => $id,
            "game_original" => $game?->original == 1 ? true : false,
            "user_balance" => self::getBalancePlayFiver(Auth::guard("api")->user()->id),
        ];

        $response = Http::withOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ])->post("https://api.playfivers.com/api/v2/game_launch", $postArray);

        if ($response->successful()) {
            $data = $response->json();

            return ["launch_url" => $data['launch_url']];
        } else {
            return ["msg" => "Erro ao lançar o jogo"];
        }
    }
    public static function webhookPlayFiverAPI(Request $request)
    {
        $tipo = strtoupper((string) $request->input('type', ''));

        // A PlayFiver pode enviar variações de maiúsculas/minúsculas; normalizamos aqui.
        switch ($tipo) {
            case 'BALANCE':
                return self::getBalancePlayFiverAPI($request);

            case 'WINBET':
                return self::percaOuGanhoPlayFiver($request);

            case 'REFUND':
                return self::refundPlayFiverApi($request);

            default:
                return response()->json(['msg' => 'INVALID_TYPE', 'balance' => 0], 422);
        }
    }

    private static function getBalancePlayFiverAPI($dados)
    {
        $user = User::where('email', (string) $dados->input('user_code'))->first();

        if (!$user) {
            return response()->json(['msg' => 'INVALID_USER', 'balance' => 0], 404);
        }

        $wallet = $user->wallet;
        $saldo = (float) ($wallet->balance ?? 0) + (float) ($wallet->balance_bonus ?? 0) + (float) ($wallet->balance_withdrawal ?? 0);

        // A PlayFiver espera um número (float) e não uma string formatada.
        return response()->json(['msg' => '', 'balance' => $saldo]);
    }

    private static function refundPlayFiverApi($dados)
    {
        self::credencialFiverPlay();

        $agentSecret = $dados->input('agent_secret')
            ?? $dados->input('secretKey')
            ?? $dados->header('agent_secret')
            ?? $dados->header('secretKey')
            ?? $dados->header('Agent-Secret');

        $agentCode = $dados->input('agent_code')
            ?? $dados->input('agentToken')
            ?? $dados->header('agent_code')
            ?? $dados->header('agentToken')
            ?? $dados->header('Agent-Code');

        $validSecret = is_string($agentSecret) && hash_equals((string) self::$secretPlayFiver, (string) $agentSecret);
        $validCode = is_string($agentCode) && (
            hash_equals((string) self::$codePlayFiver, (string) $agentCode) ||
            hash_equals((string) self::$tokenPlayFiver, (string) $agentCode)
        );

        if (!$validSecret || !$validCode) {
            Log::warning('Tentativa de acesso não autorizado ao refund webhook da PlayFiver.', [
                'ip' => request()->ip(),
                'agent_code' => $agentCode,
                'agent_secret' => $agentSecret,
                'dados' => $dados->all(),
            ]);

            return response()->json(['msg' => 'UNAUTHORIZED', 'balance' => 0], 401);
        }

$user = User::where("email", $dados->input("user_code"))->first();
        $detalhes = $dados->input($dados->input("game_type"));
        $order = Order::where("round_id", $detalhes["round_id"])->first();
        if ($order == null || $user == null) {
            return response()->json(["msg" => "INVALID_USER", "balance" => 0]);
        }
        $order->update(["refunded" => true]);
        $wallet = $user->wallet;
        $user->wallet->increment("balance_withdrawal", $detalhes['win']);
        $saldo = ((float)$wallet->balance + (float)$wallet->balance_bonus + (float)$wallet->balance_withdrawal) + $detalhes['win'];

        return response()->json(["msg" => "", "balance" => $saldo]);
    }
    private static function percaOuGanhoPlayFiver($dados)
    {
        self::credencialFiverPlay();

        // A PlayFiver pode enviar as credenciais tanto como agent_code/agent_secret
        // quanto como agentToken/secretKey (dependendo da versão/integração).
        $agentSecret = $dados->input('agent_secret')
            ?? $dados->input('secretKey')
            ?? $dados->header('agent_secret')
            ?? $dados->header('secretKey')
            ?? $dados->header('Agent-Secret');

        $agentCode = $dados->input('agent_code')
            ?? $dados->input('agentToken')
            ?? $dados->header('agent_code')
            ?? $dados->header('agentToken')
            ?? $dados->header('Agent-Code');

        $validSecret = is_string($agentSecret) && hash_equals((string) self::$secretPlayFiver, (string) $agentSecret);
        $validCode = is_string($agentCode) && (
            hash_equals((string) self::$codePlayFiver, (string) $agentCode) ||
            hash_equals((string) self::$tokenPlayFiver, (string) $agentCode)
        );

        if (!$validSecret || !$validCode) {
            Log::warning('Tentativa de acesso não autorizado ao webhook da PlayFiver.', [
                'ip' => request()->ip(),
                'agent_code' => $agentCode,
                'agent_secret' => $agentSecret,
                'dados' => $dados->all(),
            ]);

            return response()->json(['msg' => 'UNAUTHORIZED', 'balance' => 0], 401);
        }

        $user = User::where("email", $dados->input("user_code"))->first();
        if ($user != null) {
            $wallet = $user->wallet;
            $detalhes = $dados->input($dados->input("game_type"));

            $bet = $detalhes['bet'];
            $win = $detalhes['win'];
            $saldo = $wallet->balance;
            $saldoAnt = (float)$wallet->balance + (float)$wallet->balance_bonus + (float)$wallet->balance_withdrawal;                    
            $saldo = ((float)$wallet->balance + (float)$wallet->balance_bonus + (float)$wallet->balance_withdrawal) - $bet + $win;
            $changeBonus = null;
            if ($saldoAnt >= $bet) {   
                if ($wallet->balance_bonus > $bet) {
                    $wallet->decrement('balance_bonus', $bet);
                    $changeBonus = 'balance_bonus';
                } elseif ($wallet->balance >= $bet) {
                    $wallet->decrement('balance', $bet);
                    $changeBonus = 'balance';
                } elseif ($wallet->balance_withdrawal >= $bet) {
                    $wallet->decrement('balance_withdrawal', $bet);
                    $changeBonus = 'balance_withdrawal';
                } else {
                    $changeBonus = "balance";
                    if ($saldoAnt >= $bet) {
                        $valorPerdido = $bet;
                        $balanceBonus = $wallet->balance_bonus;
                        $balance = $wallet->balance;
                        $balanceWithdrawal = $wallet->balance_withdrawal;

                        if ($balanceBonus >= $valorPerdido) {
                            $balanceBonus -= $valorPerdido;
                            $valorPerdido = 0;
                        } else {
                            $valorPerdido -= $balanceBonus;
                            $balanceBonus = 0;
                        }


                        if ($balance >= $valorPerdido) {
                            $balance -= $valorPerdido;
                            $valorPerdido = 0;
                        } else {
                            $valorPerdido -= $balance;
                            $balance = 0;
                        }


                        if ($balanceWithdrawal >= $valorPerdido) {
                            $balanceWithdrawal -= $valorPerdido;
                            $valorPerdido = 0;
                        } else {
                            $valorPerdido -= $balanceWithdrawal;
                            $balanceWithdrawal = 0;
                        }


                        $wallet->update([
                            "balance_bonus" => $balanceBonus,
                            "balance" => $balance,
                            "balance_withdrawal" => $balanceWithdrawal
                        ]);
                    }
                }
                if ($bet == 0 && $win != 0) {
                    $changeBonus = "balance";
                }
                if ($bet != 0 || $win != 0) {
                    Order::create([
                        "user_id" => $user->id,
                        "session_id" => $detalhes['round_id'],
                        "transaction_id" => $detalhes['txn_id'],
                        "game" => $detalhes['game_code'],
                        "game_uuid" => $detalhes['game_code'],
                        "type" => $win == 0 ? "bet" : "win",
                        "type_money" => $changeBonus,
                        "amount" =>  $win == 0 ? $bet : $win,
                        "providers" => "play_fiver",
                        "refunded" => false,
                        "round_id" => $detalhes['round_id'],
                        "status" => true
                    ]);
                    Helper::generateGameHistory($user->id, $win == 0 ? "bet" : "win", $win, $bet, $changeBonus, $detalhes['txn_id']);
                }

                return response()->json(["msg" => "", "balance" => $saldo]);
            } else {
                return response()->json(["msg" => "INSUFFICIENT_USER_FUNDS"]);
            }
        } else {
            return response()->json(["msg" => "INVALID_USER"]);
        }
    }
    private static function getBalancePlayFiver($id)
    {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return 0;
        }

        $wallet = $user->wallet;
        return (float) ($wallet->balance ?? 0) + (float) ($wallet->balance_bonus ?? 0) + (float) ($wallet->balance_withdrawal ?? 0);
    }
}

