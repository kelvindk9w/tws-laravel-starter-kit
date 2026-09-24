<?php

declare(strict_types=1);

namespace App\Core\Auth\Contracts\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta HTTP de um fluxo de autenticação — ponto de extensão.
 *
 * Redefinição recusada pelo broker (token inválido ou vencido, e-mail sem
 * conta, limite de tentativas); `$status` é a chave de tradução do motivo.
 *
 * A regra de negócio já rodou (Actions de App\Core\Auth\Actions); a
 * resposta só decide o que devolver ao navegador ou ao cliente. A
 * implementação padrão (App\Core\Auth\Http\Responses) reproduz o
 * redirect/mensagem das telas Blade do kit; outro front troca só esta peça
 * registrando a sua no container (ver docs/autenticacao.md).
 */
interface FailedPasswordResetResponse
{
    public function toResponse(Request $request, string $status): Response;
}
