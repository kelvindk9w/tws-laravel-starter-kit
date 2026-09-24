<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta HTTP de um fluxo de autenticação — ponto de extensão.
 *
 * Senha aceita numa conta com segundo fator: estado intermediário aberto e
 * código enviado; a pessoa precisa ir à tela do código.
 *
 * A regra de negócio já rodou (Actions de Twstec\Kit\Auth\Actions); a
 * resposta só decide o que devolver ao navegador ou ao cliente. A
 * implementação padrão (Twstec\Kit\Auth\Http\Responses) reproduz o
 * redirect/mensagem das telas Blade do kit; outro front troca só esta peça
 * registrando a sua no container (ver docs/autenticacao.md).
 */
interface TwoFactorRequiredResponse
{
    public function toResponse(Request $request): Response;
}
