<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JOGO
|--------------------------------------------------------------------------
*/

Route::get('/', [GameController::class, 'index'])->name('game');

Route::post('/api/log', [GameLogController::class, 'store'])
    ->middleware('throttle:game-log')
    ->name('game.log');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {

    // Público (login)
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Protegido (painel)
    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/logs/export', [DashboardController::class, 'export'])->name('logs.export');
        Route::delete('/logs', [DashboardController::class, 'clearLogs'])->name('logs.clear');
    });
});
