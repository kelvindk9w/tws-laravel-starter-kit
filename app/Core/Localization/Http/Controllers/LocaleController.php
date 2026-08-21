<?php

declare(strict_types=1);

namespace App\Core\Localization\Http\Controllers;

use App\Core\Localization\Middleware\SetLocale;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Troca de idioma (seletor da landing/showcase/painel — ADR-007).
 *
 * Visitante: grava o cookie `locale` (1 ano). Usuário autenticado: também
 * persiste a preferência na conta (users.locale) — usada pela interface e
 * pelos e-mails transacionais. Locais fora da whitelist → 404.
 */
final class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, platform()->availableLocales, true), 404);

        $user = $request->user();

        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return redirect()->back()
            ->withCookie(cookie()->make(SetLocale::COOKIE, $locale, 60 * 24 * 365));
    }
}
