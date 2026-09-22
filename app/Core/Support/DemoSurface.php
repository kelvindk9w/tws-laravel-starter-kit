<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Support\Exceptions\DemoSurfaceInProductionException;

/**
 * A REGRA da superfície de demonstração, em um lugar só.
 *
 * PROBLEMA: o kit nasce com uma demonstração completa ligada — login demo de
 * um clique com credenciais impressas na tela, super admin demo
 * (`admin@tws.dev`, senha pública no `.env.example`), vitrine de componentes
 * em `/ui`, galeria dos e-mails em `/mail-preview` e seeders que enchem o
 * banco de dado fictício. Tudo isso é conveniência excelente em
 * desenvolvimento e é uma PORTA DOS FUNDOS em produção.
 *
 * Até aqui a única barreira era uma flag de ambiente (`DEMO_LOGIN_ENABLED`,
 * `UI_SHOWCASE_ENABLED`), com o padrão ligado no `.env.example`. Isso é
 * fail-OPEN: a proteção dependia INTEIRAMENTE de a pessoa lembrar de trocar a
 * flag. E o caminho normal de um starter kit é exatamente `cp .env.example
 * .env` — ou seja, o caminho normal levava a demonstração inteira para
 * produção. Esquecer não pode ser o mesmo que autorizar.
 *
 * SOLUÇÃO (fail-CLOSED): em `APP_ENV=production` a superfície de demonstração
 * não existe, independentemente do que a flag disser. A flag continua
 * valendo — mas como SEGUNDA barreira, para desligar a demo fora de produção,
 * não como única barreira dentro dela.
 *
 * ESCAPE HATCH: o roadmap do kit prevê uma demo pública hospedada (com reset
 * automático), que é legitimamente uma demonstração rodando em produção. Para
 * isso existe `DEMO_ALLOW_IN_PRODUCTION` — uma variável cujo nome diz
 * exatamente o que ela faz, que não tem valor padrão verdadeiro e que não
 * aparece descomentada em nenhum arquivo de exemplo. Silêncio nunca significa
 * permitido; permitido é declarado. Quando ela está ligada em produção, o
 * AppServiceProvider grava um aviso no log a cada boot: um opt-out de
 * segurança que ninguém vê deixa de ser uma decisão e volta a ser um
 * esquecimento.
 *
 * POR QUE `APP_ENV` E NÃO OUTRO SINAL: é o único sinal que o operador declara
 * sobre a própria instalação e que o Laravel já usa para decidir HTTPS
 * forçado, cache de config e comportamento de erro. Amarrar a demo a ele faz
 * a proteção viajar junto com tudo o mais que já depende de "isto é
 * produção".
 *
 * COMO A RECUSA É DADA, e por quê:
 *
 *   ROTA     → 404 (`abort_unless`), nunca 403. 403 confirma que a rota
 *              existe e está a uma flag de distância de abrir; 404 é
 *              indistinguível de rota que nunca foi escrita. As rotas
 *              CONTINUAM registradas (o rodapé e o menu do site geram
 *              `route('ui.showcase')` incondicionalmente — desregistrar
 *              derrubaria a home inteira com RouteNotFoundException).
 *
 *   SEEDER   → exceção, quando chamado DIRETAMENTE (ver
 *              DemoSurfaceInProductionException).
 *
 *   AGREGADOR→ o DatabaseSeeder pula o bloco demo com aviso no console, em vez
 *              de estourar: ele é o que um script de deploy roda, e recusar o
 *              deploy inteiro por causa de dado de demonstração seria trocar
 *              uma armadilha por outra.
 */
final class DemoSurface
{
    /**
     * A superfície de demonstração pode existir neste ambiente?
     *
     * Fora de produção: sim (é para isso que ela serve). Em produção: só com
     * o opt-out declarado.
     */
    public static function allowed(): bool
    {
        return ! app()->isProduction() || self::allowedInProduction();
    }

    /**
     * O opt-out explícito está declarado? (`DEMO_ALLOW_IN_PRODUCTION`)
     *
     * Lido de configuração, e não de `env()` direto, para continuar
     * funcionando com o config cacheado do deploy.
     */
    public static function allowedInProduction(): bool
    {
        return (bool) config('ui.demo.allow_in_production', false);
    }

    /**
     * Produção com a demonstração liberada por decisão explícita — o estado
     * que merece aviso no log.
     */
    public static function allowedInProductionByOptOut(): bool
    {
        return app()->isProduction() && self::allowedInProduction();
    }

    /**
     * Login demo: credenciais conhecidas pré-preenchidas (e impressas) na
     * tela de login do painel e do `/admin`, e os seeders das contas demo.
     */
    public static function loginEnabled(): bool
    {
        return self::allowed() && (bool) config('ui.demo_login.enabled');
    }

    /**
     * Vitrine de componentes (`/ui` e o demo de formulário `POST /ui/form-demo`).
     */
    public static function showcaseEnabled(): bool
    {
        return self::allowed() && (bool) config('ui.showcase_enabled');
    }

    /**
     * Galeria dos e-mails transacionais (`/mail-preview`).
     *
     * Compartilha a flag do login demo de propósito (as duas são a mesma
     * decisão: "conveniência de dev que vira risco em produção"), mas tem
     * método próprio para que o ponto de leitura diga qual tela é.
     */
    public static function mailPreviewEnabled(): bool
    {
        return self::loginEnabled();
    }

    /**
     * Portão dos seeders de demonstração: deixa passar ou lança.
     *
     * Chamado na PRIMEIRA linha do `run()` de todo seeder que cria dado
     * fictício. Note que o portão NÃO consulta as flags — um seeder que foi
     * invocado à mão já é a intenção declarada de semear; o que ele precisa
     * verificar é apenas se o ambiente aceita dado de demonstração.
     */
    public static function ensureSeedingAllowed(string $surface): void
    {
        if (! self::allowed()) {
            throw DemoSurfaceInProductionException::seeder($surface);
        }
    }
}
