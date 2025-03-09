<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Health check endpoint que verifica o estado de todos os componentes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check()
    {
        // Início da medição de tempo
        $startTime = microtime(true);

        // Verificar todos os componentes
        $healthData = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'components' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'gateway1' => $this->checkGateway('Gateway 1', config('services.gateway1.url')),
                'gateway2' => $this->checkGateway('Gateway 2', config('services.gateway2.url')),
            ],
            'metrics' => $this->collectMetrics(),
        ];

        // Determinar o status geral baseado nos componentes
        foreach ($healthData['components'] as $component) {
            if ($component['status'] === 'error') {
                $healthData['status'] = 'degraded';
            }
        }

        // Adicionar tempo de resposta
        $healthData['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json($healthData);
    }

    /**
     * Verificar conexão com o banco de dados
     *
     * @return array
     */
    private function checkDatabase()
    {
        try {
            // Verificar conexão com banco
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Executar uma consulta simples para testar a performance
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $queryTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'ok',
                'connection_time_ms' => $connectionTime,
                'query_time_ms' => $queryTime,
                'connection' => DB::connection()->getName(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connection' => DB::connection()->getName(),
            ];
        }
    }

    /**
     * Verificar conexão com Redis
     *
     * @return array
     */
    private function checkRedis()
{
    try {
        if (!extension_loaded('redis')) {
            return [
                'status' => 'warning',
                'message' => 'PHP Redis extension not installed',
            ];
        }

        $startTime = microtime(true);

        $testKey = 'health:check:' . time();
        Cache::store('redis')->put($testKey, 'ok', 10);
        $stored = Cache::store('redis')->get($testKey);

        if ($stored !== 'ok') {
            throw new \Exception('Redis store/retrieve test failed');
        }

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status' => 'ok',
            'response_time_ms' => $responseTime,
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
        ];
    }
}

    /**
     * Verificar conexão com Gateway de pagamento
     *
     * @param string $name
     * @param string $url
     * @return array
     */
    private function checkGateway($name, $url)
    {
        try {
            $startTime = microtime(true);

            // Verificar apenas conectividade com o gateway (não autenticar)
            $response = Http::timeout(5)->get($url);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful() || $response->status() === 404) {
                // 404 é aceitável pois só estamos testando se o servidor responde
                return [
                    'status' => 'ok',
                    'response_time_ms' => $responseTime,
                    'url' => $url,
                ];
            } else {
                return [
                    'status' => 'error',
                    'response_code' => $response->status(),
                    'url' => $url,
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'url' => $url,
            ];
        }
    }

    /**
     * Coletar métricas básicas do sistema
     *
     * @return array
     */
    private function collectMetrics()
    {
        try {
            return [
                'transactions' => [
                    'total' => Transaction::count(),
                    'completed' => Transaction::where('status', 'COMPLETED')->count(),
                    'refunded' => Transaction::where('status', 'REFUNDED')->count(),
                    'last_24h' => Transaction::where('created_at', '>=', now()->subDay())->count()
                ],
                'gateways' => [
                    'active' => Gateway::where('is_active', true)->count(),
                    'inactive' => Gateway::where('is_active', false)->count(),
                    'transactions_by_gateway' => $this->getTransactionsByGateway()
                ],
                'products' => [
                    'count' => Product::count(),
                    'average_price' => round(Product::avg('amount') / 100, 2)
                ],
                'users' => [
                    'total' => User::count(),
                    'roles_distribution' => $this->getUserRolesDistribution()
                ],
                'clients' => [
                    'total' => Client::count(),
                    'with_transactions' => Client::has('transactions')->count()
                ]
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to collect metrics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obter distribuição de transações por gateway
     *
     * @return array
     */
    private function getTransactionsByGateway()
    {
        $result = [];
        $gatewayTransactions = Transaction::select('gateway_id', DB::raw('count(*) as total'))
            ->groupBy('gateway_id')
            ->get();

        foreach ($gatewayTransactions as $item) {
            $gateway = Gateway::find($item->gateway_id);
            $gatewayName = $gateway ? $gateway->name : "Unknown (ID: {$item->gateway_id})";
            $result[$gatewayName] = $item->total;
        }

        return $result;
    }

    /**
     * Obter distribuição de usuários por role
     *
     * @return array
     */
    private function getUserRolesDistribution()
    {
        $result = [];
        $roleDistribution = DB::table('role_user')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->select('roles.name', DB::raw('count(*) as total'))
            ->groupBy('roles.name')
            ->get();

        foreach ($roleDistribution as $item) {
            $result[$item->name] = $item->total;
        }

        return $result;
    }

    /**
     * Health check detalhado apenas do sistema de pagamentos
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentSystem()
    {
        // Coletando métricas específicas para o sistema de pagamentos
        $startTime = microtime(true);

        $lastDay = now()->subDay();
        $lastWeek = now()->subWeek();
        $lastMonth = now()->subMonth();

        $data = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'gateways' => $this->getGatewayStatuses(),
            'transaction_metrics' => [
                'total_processed' => Transaction::count(),
                'volume_total' => Transaction::sum('amount') / 100, // Em reais ao invés de centavos
                'success_rate' => $this->calculateSuccessRate(),
                'time_periods' => [
                    'last_24h' => [
                        'count' => Transaction::where('created_at', '>=', $lastDay)->count(),
                        'volume' => Transaction::where('created_at', '>=', $lastDay)->sum('amount') / 100,
                    ],
                    'last_7d' => [
                        'count' => Transaction::where('created_at', '>=', $lastWeek)->count(),
                        'volume' => Transaction::where('created_at', '>=', $lastWeek)->sum('amount') / 100,
                    ],
                    'last_30d' => [
                        'count' => Transaction::where('created_at', '>=', $lastMonth)->count(),
                        'volume' => Transaction::where('created_at', '>=', $lastMonth)->sum('amount') / 100,
                    ],
                ],
                'refunds' => [
                    'count' => Transaction::where('status', 'REFUNDED')->count(),
                    'volume' => Transaction::where('status', 'REFUNDED')->sum('amount') / 100,
                    'rate' => $this->calculateRefundRate(),
                ],
            ],
            'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ];

        return response()->json($data);
    }

    /**
     * Obter status de todos os gateways
     *
     * @return array
     */
    private function getGatewayStatuses()
    {
        $gateways = Gateway::all();
        $result = [];

        foreach ($gateways as $gateway) {
            $gatewayUrl = '';
            if ($gateway->type === 'gateway1') {
                $gatewayUrl = config('services.gateway1.url');
            } elseif ($gateway->type === 'gateway2') {
                $gatewayUrl = config('services.gateway2.url');
            }

            // Verificar conectividade
            $status = 'unknown';
            $responseTime = null;

            try {
                $startTime = microtime(true);
                $response = Http::timeout(3)->get($gatewayUrl);
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $status = ($response->successful() || $response->status() === 404) ? 'online' : 'error';
            } catch (\Exception $e) {
                $status = 'offline';
            }

            // Sucesso por gateway
            $totalTransactions = Transaction::where('gateway_id', $gateway->id)->count();
            $successfulTransactions = Transaction::where('gateway_id', $gateway->id)
                ->where('status', 'COMPLETED')
                ->count();

            $successRate = $totalTransactions > 0
                ? round(($successfulTransactions / $totalTransactions) * 100, 2)
                : 0;

            $result[] = [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'type' => $gateway->type,
                'is_active' => $gateway->is_active,
                'priority' => $gateway->priority,
                'status' => $status,
                'response_time_ms' => $responseTime,
                'transactions' => [
                    'total' => $totalTransactions,
                    'success_rate' => $successRate . '%',
                ],
            ];
        }

        return $result;
    }

    /**
     * Calcular taxa de sucesso geral
     *
     * @return string
     */
    private function calculateSuccessRate()
    {
        $total = Transaction::count();
        if ($total === 0) {
            return '0.00%';
        }

        $successful = Transaction::where('status', 'COMPLETED')->count();
        return round(($successful / $total) * 100, 2) . '%';
    }

    /**
     * Calcular taxa de reembolso
     *
     * @return string
     */
    private function calculateRefundRate()
    {
        $total = Transaction::count();
        if ($total === 0) {
            return '0.00%';
        }

        $refunded = Transaction::where('status', 'REFUNDED')->count();
        return round(($refunded / $total) * 100, 2) . '%';
    }
}
