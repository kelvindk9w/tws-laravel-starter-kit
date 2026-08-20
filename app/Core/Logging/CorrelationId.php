<?php

declare(strict_types=1);

namespace App\Core\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ID de correlação da requisição (ADR-004).
 *
 * Cada requisição recebe um identificador único (UUID v7 ordenado),
 * propagado por todo o fluxo:
 * - atributo da requisição (Request::attributes);
 * - contexto compartilhado do Monolog (Log::shareContext) — todo Log::*
 *   emitido durante a requisição carrega o correlation_id;
 * - header X-Correlation-Id na resposta (rastreabilidade pelo cliente).
 *
 * Um X-Correlation-Id de ENTRADA só é aceito se for um UUID válido
 * (permite correlacionar chamadas em cadeia sem aceitar valores arbitrários).
 */
final class CorrelationId
{
    /**
     * Nome do atributo na requisição e da chave no contexto de log.
     */
    public const ATTRIBUTE = 'correlation_id';

    /**
     * Header HTTP de propagação.
     */
    public const HEADER = 'X-Correlation-Id';

    /**
     * Resolve (ou gera) o correlation_id da requisição e o propaga
     * para o contexto de log. Idempotente: chamadas seguintes na mesma
     * requisição retornam o mesmo valor.
     */
    public static function resolve(Request $request): string
    {
        $existing = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $incoming = $request->header(self::HEADER);

        $id = is_string($incoming) && Str::isUuid($incoming)
            ? $incoming
            : (string) Str::uuid7();

        $request->attributes->set(self::ATTRIBUTE, $id);
        Log::shareContext([self::ATTRIBUTE => $id]);

        return $id;
    }
}
