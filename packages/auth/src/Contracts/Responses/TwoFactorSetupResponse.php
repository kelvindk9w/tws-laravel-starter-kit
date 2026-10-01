<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta de quando a configuração OBRIGATÓRIA do segundo fator terminou
 * (ou já não havia o que configurar): a pessoa segue para onde queria ir.
 *
 * Padrão: o destino guardado antes de ela ser levada à configuração, pelo
 * SafeRedirect (destino fora da aplicação cai no painel). Um front Inertia
 * troca por uma carga completa (o destino pode ser o /admin, que não é
 * página do front).
 */
interface TwoFactorSetupResponse
{
    public function toResponse(Request $request): Response;
}
