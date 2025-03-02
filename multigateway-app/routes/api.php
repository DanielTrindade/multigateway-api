<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\GatewayController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rotas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/purchase', [TransactionController::class, 'purchase']);

// Rotas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Usuário atual
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Gateways
    Route::controller(GatewayController::class)->prefix('gateways')->group(function () {
        Route::get('/', 'index');
        Route::get('/{gateway}', 'show');
        Route::post('/', 'store');
        Route::put('/{gateway}', 'update');
        Route::delete('/{gateway}', 'destroy');
        Route::patch('/{gateway}/toggle', 'toggleActive');
        Route::patch('/{gateway}/priority', 'updatePriority');
    });

    // Produtos
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('/', 'index');
        Route::get('/{product}', 'show');
        Route::post('/', 'store');
        Route::put('/{product}', 'update');
        Route::delete('/{product}', 'destroy');
    });

    // Clientes
    Route::controller(ClientController::class)->prefix('clients')->group(function () {
        Route::get('/', 'index');
        Route::get('/{client}', 'show');
        Route::get('/{client}/transactions', 'transactions');
    });

    // Transações
    Route::controller(TransactionController::class)->prefix('transactions')->group(function () {
        Route::get('/', 'index');
        Route::get('/{transaction}', 'show');
        Route::post('/{transaction}/refund', 'refund');
    });


    // Usuários
    Route::controller(UserController::class)->prefix('users')->group(function () {
        Route::get('/', 'index');
        Route::get('/{user}', 'show');
        Route::post('/', 'store');
        Route::put('/{user}', 'update');
        Route::delete('/{user}', 'destroy');
        Route::patch('/{user}/role', 'updateRole');
    });
});
