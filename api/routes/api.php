<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {

    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::prefix('auth')->name('auth.')->group(function () {
        // Public
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');

        // Protected 
        Route::middleware('jwt.access')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    // Protected
    Route::middleware('jwt.access')->group(function () {
        //
    });
});
