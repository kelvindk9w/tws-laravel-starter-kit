<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta HTTP de um fluxo de autenticação — ponto de extensão.
 *
 * Sessão ENCERRADA porque a senha ou o segundo fator da conta mudaram em
 * outro lugar (Http\Middleware\EnsureSessionIsCurrent) — já deslogada,
 * invalidada e com token CSRF novo.
 *
 * O padrão (Twstec\Kit\Auth\Http\Responses\SessionEndedResponse) volta ao
 * login do aplicativo com o aviso. Outra superfície com login próprio — o
 * /admin do twstec/kit-admin — decora esta peça para mandar ao login DELA
 * quem estava nela.
 */
interface SessionEndedResponse
{
    public function toResponse(Request $request): Response;
}
