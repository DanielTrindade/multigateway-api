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
    Route::get('/gateways', [GatewayController::class, 'index']);
    Route::get('/gateways/{gateway}', [GatewayController::class, 'show']);
    Route::post('/gateways', [GatewayController::class, 'store']);
    Route::put('/gateways/{gateway}', [GatewayController::class, 'update']);
    Route::delete('/gateways/{gateway}', [GatewayController::class, 'destroy']);
    Route::patch('/gateways/{gateway}/toggle', [GatewayController::class, 'toggleActive']);
    Route::patch('/gateways/{gateway}/priority', [GatewayController::class, 'updatePriority']);

    // Produtos
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    // Clientes
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::get('/clients/{client}/transactions', [ClientController::class, 'transactions']);

    // Transações
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund']);

    // Usuários
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
});
