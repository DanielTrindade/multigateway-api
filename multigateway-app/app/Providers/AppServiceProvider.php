<?php

namespace App\Providers;

use App\Models\Gateway;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Event listeners para Gateway - limpar cache quando gateway for modificado
        Gateway::updated(function ($gateway) {
            // Limpar cache quando um gateway é atualizado
            Cache::store('redis')->forget('active_gateways_list');
        });

        Gateway::created(function ($gateway) {
            Cache::store('redis')->forget('active_gateways_list');
        });

        Gateway::deleted(function ($gateway) {
            Cache::store('redis')->forget('active_gateways_list');
        });

        // Event listeners para Transaction - limpar cache de paginação quando nova transação for criada
        Transaction::created(function ($transaction) {
            $page = 1; // Primeira página é a mais acessada
            Cache::store('redis')->forget("transactions_page_{$page}");
        });

        Transaction::updated(function ($transaction) {
            // Se o status da transação mudou (ex: para REFUNDED)
            if ($transaction->isDirty('status')) {
                // Limpar cache da transação específica
                Cache::store('redis')->forget("transaction_{$transaction->id}");

                // Limpar cache da primeira página de transações (mais acessada)
                Cache::store('redis')->forget("transactions_page_1");
            }
        });
    }
}
