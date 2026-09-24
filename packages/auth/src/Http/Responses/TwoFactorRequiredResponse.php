<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorRequiredResponse as TwoFactorRequiredResponseContract;

/**
 * Padrão: leva à tela do código.
 */
final class TwoFactorRequiredResponse implements TwoFactorRequiredResponseContract
{
    public function toResponse(Request $request): Response
    {
        return redirect()->route('two-factor.challenge');
    }
}
