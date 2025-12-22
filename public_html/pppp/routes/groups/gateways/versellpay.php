<?php

use App\Http\Controllers\Gateway\VersellPayController;
use Illuminate\Support\Facades\Route;

Route::prefix('versellpay')
    ->group(function () {
        Route::any('/callback', [VersellPayController::class, 'callback']);
    });
