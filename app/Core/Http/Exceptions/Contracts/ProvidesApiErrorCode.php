<?php

declare(strict_types=1);

namespace App\Core\Http\Exceptions\Contracts;

/**
 * Exceção HTTP que traz um `code` PRÓPRIO para o envelope de erro da API, mais
 * específico que o código do status (ex.: `api_key_scope_exceeded` em vez de
 * `forbidden`). O ApiErrorRenderer usa este código no lugar do padrão; o
 * status, a mensagem e o resto do envelope seguem as regras de sempre.
 *
 * O código é contrato público: estável, em inglês, nunca traduzido.
 */
interface ProvidesApiErrorCode
{
    public function apiErrorCode(): string;
}
