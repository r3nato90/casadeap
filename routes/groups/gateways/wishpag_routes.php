<?php

use App\Http\Controllers\Gateway\WishPagGatewayController;
use Illuminate\Support\Facades\Route;

Route::prefix('wishpag')
    ->group(function () {
        // Criar pagamento
        Route::post('/payment', [WishPagGatewayController::class, 'createPayment'])
            ->name('wishpag.payment')
            ->middleware('auth.jwt');

        // Listar transações
        Route::get('/transactions', [WishPagGatewayController::class, 'listTransactions'])
            ->name('wishpag.transactions');

        // Consultar transação específica
        Route::get('/transactions/{id}', [WishPagGatewayController::class, 'getTransaction'])
            ->name('wishpag.transaction.show');

        // Estornar transação
        Route::delete('/transactions/{id}', [WishPagGatewayController::class, 'refundTransaction'])
            ->name('wishpag.transaction.refund');

        // Atualizar status de entrega
        Route::put('/transactions/{id}/delivery', [WishPagGatewayController::class, 'updateDeliveryStatus'])
            ->name('wishpag.transaction.delivery');

        // Tokenização de cartão
        Route::post('/card/tokenize', [WishPagGatewayController::class, 'tokenizeCard'])
            ->name('wishpag.card.tokenize');

        // Callback/Webhook
        Route::any('/callback', [WishPagGatewayController::class, 'callback'])
            ->name('wishpag.callback');
    });
