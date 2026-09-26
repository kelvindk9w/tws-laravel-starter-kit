<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Os endereços que as páginas React usam, pelo NOME da rota.
 *
 * O kit oficial gera funções TypeScript das rotas com o Wayfinder, que roda
 * `php artisan` DURANTE o build do front. No kit o build roda num container
 * só de Node (sem PHP) e na imagem de produção num estágio só de Node — o
 * Wayfinder não cabe. No lugar dele, o servidor manda o mapa nome → caminho
 * (relativo) das rotas desta lista, e o front pergunta `route('login')`
 * (resources/js/lib/routes.ts). Nenhuma URL escrita no TypeScript, e a rota
 * de um módulo ausente simplesmente não está no mapa.
 *
 * Só nomes, nunca parâmetros: rota com parâmetro (link de e-mail, redefinição
 * de senha) chega pronta como prop da página que precisa dela.
 */
final class FrontRoutes
{
    /**
     * @var list<string>
     */
    public const NAMES = [
        'home',
        'login',
        'register',
        'logout',
        'password.request',
        'password.email',
        'password.update',
        'two-factor.challenge',
        'two-factor.resend',
        'two-factor.cancel',
        'verification.notice',
        'verification.send',
        'dashboard',
        'panel.profile',
        'panel.profile.update',
        'panel.password.update',
        'panel.notifications',
        'panel.notifications.update',
        'panel.two-factor.code',
        'panel.two-factor.update',
        'transaction-password.edit',
        'transaction-password.update',
        'settings.theme',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $routes = [];

        foreach (self::NAMES as $name) {
            if (Route::has($name)) {
                $routes[$name] = route($name, absolute: false);
            }
        }

        return $routes;
    }
}
