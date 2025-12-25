<?php

use App\Http\Controllers\Gateway\WishPagController;
use Illuminate\Support\Facades\Route;

Route::prefix('wishpag')
    ->group(function () {
        Route::any('/callback', [WishPagController::class, 'callback']);
    });
