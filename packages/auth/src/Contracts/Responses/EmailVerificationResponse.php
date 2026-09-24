<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Support\EmailVerificationResult;

/**
 * Resposta HTTP de um fluxo de autenticação — ponto de extensão.
 *
 * Qualquer outro resultado da verificação de e-mail: nada pendente, link de
 * outra conta, link inválido, link reenviado ou reenvio em espera.
 *
 * A regra de negócio já rodou (Actions de Twstec\Kit\Auth\Actions); a
 * resposta só decide o que devolver ao navegador ou ao cliente. A
 * implementação padrão (Twstec\Kit\Auth\Http\Responses) reproduz o
 * redirect/mensagem das telas Blade do kit; outro front troca só esta peça
 * registrando a sua no container (ver docs/autenticacao.md).
 */
interface EmailVerificationResponse
{
    public function toResponse(Request $request, EmailVerificationResult $result): Response;
}
