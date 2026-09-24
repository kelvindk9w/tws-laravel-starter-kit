<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * Resposta HTTP de um fluxo de autenticação — ponto de extensão.
 *
 * Cadastro concluído: a conta foi criada, a sessão autenticada e, com a
 * verificação de e-mail ligada, o link enviado.
 *
 * A regra de negócio já rodou (Actions de Twstec\Kit\Auth\Actions); a
 * resposta só decide o que devolver ao navegador ou ao cliente. A
 * implementação padrão (Twstec\Kit\Auth\Http\Responses) reproduz o
 * redirect/mensagem das telas Blade do kit; outro front troca só esta peça
 * registrando a sua no container (ver docs/autenticacao.md).
 */
interface RegisterResponse
{
    public function toResponse(Request $request, AuthUser $user): Response;
}
