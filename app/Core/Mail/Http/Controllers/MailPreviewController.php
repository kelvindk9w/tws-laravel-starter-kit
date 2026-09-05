<?php

declare(strict_types=1);

namespace App\Core\Mail\Http\Controllers;

use App\Core\Mail\MailPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Pré-visualização dos e-mails transacionais (/mail-preview) — ferramenta de
 * DESENVOLVIMENTO.
 *
 * Fica atrás da mesma flag do login demo (config/ui.php ← DEMO_LOGIN_ENABLED,
 * padrão: só em APP_ENV=local). Em produção a rota responde 404: uma galeria
 * pública com o desenho de todos os e-mails da plataforma é material pronto
 * para quem quiser montar um phishing convincente.
 *
 * Três formatos na mesma rota:
 *   (padrão) a galeria, no layout do site do kit;
 *   ?format=html o e-mail cru, sozinho na janela (é o que o iframe carrega);
 *   ?format=text a versão em texto puro, como ela sai no multipart.
 */
final class MailPreviewController
{
    public function __invoke(Request $request, ?string $slug = null): View|Response
    {
        abort_unless(self::enabled(), 404);

        $slugs = MailPreview::slugs();
        $slug ??= $slugs[0];

        abort_unless(in_array($slug, $slugs, true), 404);

        $locale = $this->locale($request);
        $dark = $request->query('scheme') === 'dark';
        $email = MailPreview::render($slug, $locale, $dark);

        return match ($request->query('format')) {
            'html' => response($email['html'])->header('Content-Type', 'text/html; charset=utf-8'),
            'text' => response($email['text'])->header('Content-Type', 'text/plain; charset=utf-8'),
            default => view('mail.preview', [
                'slugs' => $slugs,
                'current' => $slug,
                'email' => $email,
                'previewLocale' => $locale,
                'dark' => $dark,
            ]),
        };
    }

    /**
     * A flag é a MESMA do login demo: as duas são "conveniências de dev que
     * viram risco em produção", e duas flags para a mesma decisão acabam
     * desalinhadas.
     */
    public static function enabled(): bool
    {
        return (bool) config('ui.demo_login.enabled');
    }

    /**
     * Idioma da PRÉ-VISUALIZAÇÃO — independente do idioma de quem navega:
     * o ponto da tela é justamente conferir os três.
     */
    private function locale(Request $request): string
    {
        $requested = (string) $request->query('lang', '');

        return in_array($requested, platform()->availableLocales, true)
            ? $requested
            : app()->getLocale();
    }
}
