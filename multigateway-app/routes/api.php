
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GatewayController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\UserController;

// Rotas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/purchase', [TransactionController::class, 'purchase']);

// Rotas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Gateways
    Route::apiResource('gateways', GatewayController::class);
    Route::patch('/gateways/{gateway}/toggle', [GatewayController::class, 'toggleActive']);
    Route::patch('/gateways/{gateway}/priority', [GatewayController::class, 'updatePriority']);

    // Products
    Route::apiResource('products', ProductController::class);

    // Clients
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::get('/clients/{client}/transactions', [ClientController::class, 'transactions']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund']);

    // Users
    Route::apiResource('users', UserController::class);
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
});
