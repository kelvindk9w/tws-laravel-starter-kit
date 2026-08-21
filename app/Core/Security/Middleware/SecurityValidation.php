<?php

declare(strict_types=1);

namespace App\Core\Security\Middleware;

use App\Core\Logging\CorrelationId;
use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Models\RequestLog;
use App\Core\Logging\Redactor;
use App\Core\Security\AttackDetector;
use App\Core\Security\PayloadSanitizer;
use App\Core\Security\RequestInputs;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Validação de segurança de entrada — PRIMEIRO middleware da cadeia global
 * (web e api), conforme ADR-004/005.
 *
 * Pipeline: receber → validação de segurança → sanitização/redaction → persistir.
 *
 * Comportamento:
 * - Detecta padrões maliciosos (XSS, SQLi, null bytes, path traversal) em
 *   query + corpo + nomes de arquivos enviados.
 * - NUNCA bloqueia silenciosamente: grava request log com status BLOQUEADA,
 *   payload SANITIZADO/escapado (nunca executável) + metadados da tentativa
 *   (IP, endpoint, tipo de ataque), e registra no canal de log dedicado.
 * - Responde 422 padronizado com mensagem genérica (não revela o que foi
 *   detectado — item 32 do checklist) + X-Correlation-Id.
 * - É também quem resolve o correlation_id da requisição (primeira peça da
 *   cadeia), propagando para os demais middlewares e logs.
 *
 * Delegação (config security.validation): os formulários demo do /ui são
 * uma vitrine de defesa EM CAMADAS — para eles, a detecção é feita pela
 * própria aplicação (o MESMO AttackDetector), que registra a tentativa em
 * form_submissions com payload inerte. Isso vale para o path do POST
 * clássico (delegated_paths) e para updates Livewire em que TODOS os
 * componentes envolvidos são autodefendidos (delegated_components).
 */
final class SecurityValidation
{
    public function __construct(
        private readonly AttackDetector $detector,
        private readonly PayloadSanitizer $sanitizer,
        private readonly Redactor $redactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::resolve($request);

        $attackType = $this->detector->detect(RequestInputs::extract($request));

        if ($attackType !== null) {
            // Rotas/componentes delegados: a camada da aplicação roda o MESMO
            // detector e registra a tentativa (vitrine de segurança do /ui).
            if ($this->isDelegated($request)) {
                return $next($request);
            }

            $this->registerBlockedAttempt($request, $correlationId, $attackType);

            return response()
                ->json([
                    'message' => __('security.blocked'),
                    'correlation_id' => $correlationId,
                ], 422)
                ->header(CorrelationId::HEADER, $correlationId);
        }

        return $next($request);
    }

    /**
     * A detecção é delegada à camada da aplicação? Vale quando o path está
     * em delegated_paths OU quando é um update Livewire em que TODOS os
     * componentes envolvidos estão em delegated_components (autodefendidos:
     * rodam o mesmo AttackDetector e registram a tentativa).
     */
    private function isDelegated(Request $request): bool
    {
        /** @var list<string> $paths */
        $paths = config('security.validation.delegated_paths', []);

        if ($paths !== [] && $request->is(...$paths)) {
            return true;
        }

        if (! $request->is('livewire/*', 'admin/livewire/*')) {
            return false;
        }

        /** @var list<string> $allowed */
        $allowed = config('security.validation.delegated_components', []);

        if ($allowed === []) {
            return false;
        }

        /** @var mixed $components */
        $components = $request->input('components', []);

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                return false;
            }

            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);
            $name = is_array($snapshot) ? ($snapshot['memo']['name'] ?? null) : null;

            if (! is_string($name) || ! in_array($name, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Persiste a tentativa de ataque: payload sanitizado/escapado + redigido
     * (dupla camada — ADR-005), com os metadados da tentativa.
     */
    private function registerBlockedAttempt(Request $request, string $correlationId, string $attackType): void
    {
        $payload = $this->redactor->redactArray(
            $this->sanitizer->sanitize(RequestInputs::extract($request)),
        );

        $context = [
            'correlation_id' => $correlationId,
            'attack_type' => $attackType,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'endpoint' => $request->path(),
        ];

        try {
            RequestLog::query()->create([
                'correlation_id' => $correlationId,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'method' => $request->method(),
                'endpoint' => Str::limit($request->path(), 2000, ''),
                'payload' => $payload,
                'status' => RequestLogStatus::Bloqueada,
                'attack_type' => $attackType,
                'http_status_response' => 422,
                'error_message' => __('security.blocked_log', ['type' => $attackType]),
            ]);
        } catch (Throwable $exception) {
            // Falha de banco NÃO pode impedir o bloqueio nem apagar a evidência:
            // a trilha de arquivo sobrevive à falha do banco (pesquisa §2.3).
            Log::channel('request_log')->critical('security.blocked.persist_failed', [
                ...$context,
                'error' => $exception->getMessage(),
            ]);
        }

        Log::channel('request_log')->warning('security.blocked', $context);
    }
}
