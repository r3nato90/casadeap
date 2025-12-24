<?php

use App\Http\Controllers\Gateway\PlayFiverGatewayController;
use Illuminate\Support\Facades\Route;

Route::prefix('playfiver')
    ->group(function () {
        // Listar provedores
        Route::get('/providers', [PlayFiverGatewayController::class, 'listProviders'])
            ->name('playfiver.providers');

        // Listar jogos
        Route::get('/games', [PlayFiverGatewayController::class, 'listGames'])
            ->name('playfiver.games');

        // Iniciar jogo
        Route::post('/launch', [PlayFiverGatewayController::class, 'launchGame'])
            ->name('playfiver.launch')
            ->middleware('auth.jwt');

        // Rodadas grátis
        Route::post('/free-bonus', [PlayFiverGatewayController::class, 'freeBonus'])
            ->name('playfiver.freebonus')
            ->middleware('auth.jwt');

        // Saldo das carteiras
        Route::get('/balances', [PlayFiverGatewayController::class, 'getBalances'])
            ->name('playfiver.balances');

        // Informações do agente
        Route::get('/agent', [PlayFiverGatewayController::class, 'getAgentInfo'])
            ->name('playfiver.agent');

        // Webhook - Saldo
        Route::post('/webhook/balance', [PlayFiverGatewayController::class, 'webhookBalance'])
            ->name('playfiver.webhook.balance');

        // Webhook - Transações
        Route::post('/webhook/transaction', [PlayFiverGatewayController::class, 'webhookTransaction'])
            ->name('playfiver.webhook.transaction');
    });
