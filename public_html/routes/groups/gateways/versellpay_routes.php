<?php

use App\Http\Controllers\Gateway\VersellPayGatewayController;
use Illuminate\Support\Facades\Route;

Route::prefix('versellpay')
    ->group(function () {
        // Gerar PIX (Cash-in)
        Route::post('/pix/generate', [VersellPayGatewayController::class, 'generatePix'])
            ->name('versellpay.pix.generate')
            ->middleware('auth.jwt');

        // Transferência PIX (Cash-out)
        Route::post('/pix/transfer', [VersellPayGatewayController::class, 'pixTransfer'])
            ->name('versellpay.pix.transfer')
            ->middleware('auth.jwt');

        // Consultar transação
        Route::post('/transaction/consult', [VersellPayGatewayController::class, 'consultTransaction'])
            ->name('versellpay.transaction.consult');

        // Consultar saldo
        Route::get('/balance', [VersellPayGatewayController::class, 'getBalance'])
            ->name('versellpay.balance')
            ->middleware('auth.jwt');

        // Callback/Webhook
        Route::any('/callback', [VersellPayGatewayController::class, 'callback'])
            ->name('versellpay.callback');
    });
