<?php

declare(strict_types=1);

namespace App\Core\Auth\Contracts\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta HTTP de um fluxo de autenticação — ponto de extensão.
 *
 * Login por senha concluído, sem segundo fator (a sessão já está autenticada,
 * com ID novo).
 *
 * A regra de negócio já rodou (Actions de App\Core\Auth\Actions); a
 * resposta só decide o que devolver ao navegador ou ao cliente. A
 * implementação padrão (App\Core\Auth\Http\Responses) reproduz o
 * redirect/mensagem das telas Blade do kit; outro front troca só esta peça
 * registrando a sua no container (ver docs/autenticacao.md).
 */
interface LoginResponse
{
    public function toResponse(Request $request): Response;
}
