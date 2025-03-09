<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\GatewayController;
use App\Http\Controllers\API\HealthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rotas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/purchase', [TransactionController::class, 'purchase']);
//health checks
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment(),
        'version' => config('app.version', '1.0.0'),
    ]);
});


Route::middleware('auth:sanctum')->prefix('health')->group(function () {
    Route::get('/system', [HealthController::class, 'check'])
        ->name('health.system');

    Route::get('/payment', [HealthController::class, 'paymentSystem'])
        ->name('health.paymentSystem')
        ->middleware('can:is-admin,is-finance');
});

// Rotas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Gateways
    Route::controller(GatewayController::class)->prefix('gateways')->group(function () {
        Route::get('/', 'index')->name('gateways.index');
        Route::get('/{gateway}', 'show')->name('gateways.show');
        Route::post('/', 'store')->name('gateways.store');
        Route::put('/{gateway}', 'update')->name('gateways.update');
        Route::delete('/{gateway}', 'destroy')->name('gateways.destroy');
        Route::patch('/{gateway}/toggle', 'toggleActive')->name('gateways.toggle');
        Route::patch('/{gateway}/priority', 'updatePriority')->name('gateways.priority');
        Route::post('/reorder', 'reorderPriorities')->name('gateways.reorderPriorities');
        Route::post('/normalize', 'normalizePriorities')->name('gateways.normalizePriorities');
    });

    // Produtos
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('/', 'index')->name('products.index');
        Route::get('/{product}', 'show')->name('products.show');
        Route::post('/', 'store')->name('products.store');
        Route::put('/{product}', 'update')->name('products.update');
        Route::delete('/{product}', 'destroy')->name('products.destroy');
    });

    // Clientes
    Route::controller(ClientController::class)->prefix('clients')->group(function () {
        Route::get('/', 'index')->name('clients.index');
        Route::get('/{client}', 'show')->name('clients.show');
        Route::get('/{client}/transactions', 'transactions')->name('clients.transactions');
    });

    // Transações
    Route::controller(TransactionController::class)->prefix('transactions')->group(function () {
        Route::get('/', 'index')->name('transactions.index');
        Route::get('/{transaction}', 'show')->name('transactions.show');
        Route::post('/{transaction}/refund', 'refund')->name('transactions.refund');
    });

    // Usuários
    Route::controller(UserController::class)->prefix('users')->group(function () {
        Route::get('/', 'index')->name('users.index');
        Route::get('/{user}', 'show')->name('users.show');
        Route::post('/', 'store')->name('users.store');
        Route::put('/{user}', 'update')->name('users.update');
        Route::delete('/{user}', 'destroy')->name('users.destroy');
        Route::patch('/{user}/role', 'updateRole')->name('users.update-role');
    });
});
