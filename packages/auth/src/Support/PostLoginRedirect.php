<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Twstec\Kit\Foundation\Http\SafeRedirect;

/**
 * Para onde a pessoa vai depois de entrar — a mesma resposta para o login
 * direto e para o login concluído pelo segundo fator.
 *
 * O destino original passa pelo SafeRedirect, e não por
 * `redirect()->intended()` direto. O framework grava `url.intended` a partir
 * do `fullUrl()` da requisição que foi barrada — URL montada com o HOST, que é
 * dado do cliente (`Host`, e `X-Forwarded-Host` quando ele é obedecido). O
 * TrustedHosts já recusa host desconhecido antes disso, mas esta é a segunda
 * barreira, ancorada em configuração em vez de na borda: mesmo que uma
 * instalação alargue a lista de proxies ou ligue o `X-Forwarded-Host`, o
 * pós-login continua só devolvendo para DENTRO da aplicação. O preço é
 * conhecido e está documentado no SafeRedirect: instalação cuja APP_URL não
 * bate com o endereço servido (http na configuração, https na borda) perde o
 * deep link e cai no dashboard até declarar a origem em
 * SECURITY_REDIRECT_ALLOWED_ORIGINS.
 */
final class PostLoginRedirect
{
    public static function to(Request $request): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        return redirect()->to(SafeRedirect::url(
            is_string($intended) ? $intended : null,
            route('dashboard'),
        ));
    }
}
