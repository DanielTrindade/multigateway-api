<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter
{
    /**
     * Personaliza a instância do logger fornecida.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new CustomJsonFormatter());
        }
    }
}

class CustomJsonFormatter extends MonologJsonFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format(LogRecord $record): string
    {
        $normalized = $this->normalize($record->toArray());

        // Adicionar informações contextuais padrão
        $normalized['environment'] = app()->environment();
        $normalized['host'] = gethostname();
        $normalized['app_version'] = config('app.version', '1.0.0');

        // Adicionar ID da requisição para correlacionar logs
        $normalized['request_id'] = request()->header('X-Request-ID') ??
                                   (request()->header('X-Correlation-ID') ?? uniqid());

        // Adicionar contexto de usuário autenticado
        if (auth()->check()) {
            $normalized['user'] = [
                'id' => auth()->id(),
                'email' => auth()->user()->email,
            ];
        }

        // Adicionar contexto de IP e User-Agent
        if (request()->ip()) {
            $normalized['client'] = [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];
        }

        if ($this->appendNewline) {
            return $this->toJson($normalized) . "\n";
        }

        return $this->toJson($normalized);
    }
}
