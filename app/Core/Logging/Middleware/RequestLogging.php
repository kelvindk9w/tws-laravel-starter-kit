<?php

declare(strict_types=1);

namespace App\Core\Logging\Middleware;

use App\Core\Logging\CorrelationId;
use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Models\RequestLog;
use App\Core\Logging\Redactor;
use App\Core\Security\RequestInputs;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pipeline de logs de requisição (ADR-004/010) — segundo middleware da
 * cadeia global, logo após a validação de segurança.
 *
 * - RECEBIMENTO: persiste o log com status INICIADA IMEDIATAMENTE, antes de
 *   qualquer processamento de negócio, com payload já redigido (LGPD).
 * - TERMINATE: transição controlada para CONCLUIDA (HTTP < 500) ou ERRO
 *   (HTTP >= 500), com duração, status HTTP e mensagem de erro redigida.
 * - Um log que permanece INICIADA = requisição que não chegou ao fim
 *   (bug, timeout, queda, ataque) → investigar.
 * - tenant_uuid fica NULL até a Fase 4 (tenancy); log sem tenant = possível
 *   ataque (ADR-010). O gancho RequestLog::bindTenant() já existe.
 *
 * Exclusões (config security.request_logging.excluded_paths): health checks
 * (/up, /api/health) e preflights OPTIONS não geram log pesado para não
 * poluir a trilha — seguem no access log do nginx e passam normalmente
 * pela validação de segurança.
 *
 * Resiliência: se o banco falhar, a requisição NÃO é derrubada — a trilha
 * de arquivo (canal request_log, JSON estruturado) registra a falha.
 */
final class RequestLogging
{
    public function __construct(private readonly Redactor $redactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExcluded($request)) {
            return $next($request);
        }

        $correlationId = CorrelationId::resolve($request);

        $request->attributes->set('request_log_started_at', microtime(true));

        $this->persistStarted($request, $correlationId);

        $response = $next($request);

        $response->headers->set(CorrelationId::HEADER, $correlationId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->isExcluded($request)) {
            return;
        }

        $log = $request->attributes->get('request_log');

        if (! $log instanceof RequestLog) {
            return;
        }

        $startedAt = $request->attributes->get('request_log_started_at');
        $durationMs = is_float($startedAt) ? max(0, (int) round((microtime(true) - $startedAt) * 1000)) : null;

        $httpStatus = $response->getStatusCode();
        $status = $httpStatus >= 500 ? RequestLogStatus::Erro : RequestLogStatus::Concluida;

        $errorMessage = null;

        if ($status === RequestLogStatus::Erro) {
            $captured = $request->attributes->get('request_log_error');
            $errorMessage = Str::limit(
                $this->redactor->redactString(is_string($captured) && $captured !== '' ? $captured : 'Erro interno sem mensagem capturada.'),
                1000,
                '',
            );
        }

        try {
            $log->markFinished($status, $httpStatus, $errorMessage, $durationMs);
        } catch (Throwable $exception) {
            Log::channel('request_log')->critical('request.finished.persist_failed', [
                'correlation_id' => $log->correlation_id,
                'error' => $exception->getMessage(),
            ]);
        }

        Log::channel('request_log')->info('request.finished', [
            'correlation_id' => $log->correlation_id,
            'status' => $status->value,
            'http_status_response' => $httpStatus,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Grava o log INICIADA imediatamente, com payload redigido.
     */
    private function persistStarted(Request $request, string $correlationId): void
    {
        $payload = $this->redactor->redactArray(RequestInputs::extract($request));

        $context = [
            'correlation_id' => $correlationId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'endpoint' => $request->path(),
        ];

        try {
            $log = RequestLog::query()->create([
                'correlation_id' => $correlationId,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'method' => $request->method(),
                'endpoint' => Str::limit($request->path(), 2000, ''),
                'payload' => $payload,
                'status' => RequestLogStatus::Iniciada,
            ]);

            $request->attributes->set('request_log', $log);

            Log::channel('request_log')->info('request.started', $context);
        } catch (Throwable $exception) {
            Log::channel('request_log')->critical('request.started.persist_failed', [
                ...$context,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * O que fica fora do log pesado:
     * - tudo que não é api/* (o middleware é global para capturar 404 de
     *   varredura na API, mas rotas web não entram neste log nesta fase);
     * - preflights CORS (OPTIONS);
     * - rotas leves listadas em config/security.php (health checks).
     */
    private function isExcluded(Request $request): bool
    {
        if (! $request->is('api/*') || $request->isMethod('OPTIONS')) {
            return true;
        }

        /** @var list<string> $excluded */
        $excluded = config('security.request_logging.excluded_paths', []);

        return $excluded !== [] && $request->is(...$excluded);
    }
}
