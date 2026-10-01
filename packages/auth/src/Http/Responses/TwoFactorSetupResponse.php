<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorSetupResponse as TwoFactorSetupResponseContract;
use Twstec\Kit\Auth\Support\PostLoginRedirect;

/**
 * Configuração obrigatória concluída: o destino guardado (SafeRedirect) ou o
 * painel, com o aviso de que o segundo fator foi ligado.
 */
final class TwoFactorSetupResponse implements TwoFactorSetupResponseContract
{
    public function toResponse(Request $request): Response
    {
        return PostLoginRedirect::to($request)->with('status', __('auth.two_factor.setup_done'));
    }
}
