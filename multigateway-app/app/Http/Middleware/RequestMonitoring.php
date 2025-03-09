<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestMonitoring
{
    /**
     * Processa a requisição e registra métricas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Adicionar ID de Requisição se não existir
        if (!$request->hasHeader('X-Request-ID') && !$request->hasHeader('X-Correlation-ID')) {
            $requestId = uniqid('req-', true);
            $request->headers->set('X-Request-ID', $requestId);
        }

        // Capturar tempo inicial
        $startTime = microtime(true);

        // Log de entrada
        if ($request->path() !== 'api/health') { // Não logar health checks
            Log::channel('system')->debug('API Request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'query' => $request->query(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID') ?? $request->header('X-Correlation-ID'),
            ]);
        }

        // Processar requisição
        $response = $next($request);

        // Calcular tempo de resposta
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        // Adicionar headers de monitoramento na resposta
        $response->header('X-Request-ID', $request->header('X-Request-ID') ?? $request->header('X-Correlation-ID'));
        $response->header('X-Response-Time', $responseTime . 'ms');

        // Registrar métricas para API endpoints (não para assets ou health checks)
        if (strpos($request->path(), 'api/') === 0 && $request->path() !== 'api/health') {
            $statusCode = $response->getStatusCode();

            // Para status codes 4xx e 5xx, logar mais detalhes
            if ($statusCode >= 400) {
                Log::channel('system')->warning('API Response', [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => $statusCode,
                    'response_time_ms' => $responseTime,
                    'request_id' => $request->header('X-Request-ID') ?? $request->header('X-Correlation-ID'),
                    'error' => $statusCode >= 500 ? 'Server Error' : 'Client Error',
                ]);
            }
            // Para operações de escrita (POST, PUT, PATCH, DELETE) logar sempre
            elseif (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                Log::channel('system')->info('API Response', [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => $statusCode,
                    'response_time_ms' => $responseTime,
                    'request_id' => $request->header('X-Request-ID') ?? $request->header('X-Correlation-ID'),
                ]);
            }
        }

        return $response;
    }
}
