<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\TwoFactorRequiredResponse as TwoFactorRequiredResponseContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
