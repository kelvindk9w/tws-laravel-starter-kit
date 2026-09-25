<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo\Support\Exceptions;

use RuntimeException;

/**
 * Alguém pediu, em PRODUÇÃO, uma peça que só existe para demonstrar o kit:
 * semear as contas demo, semear massa fictícia, abrir a vitrine de
 * componentes, abrir a galeria de e-mails.
 *
 * Por que uma exceção e não um `return` silencioso: quem roda
 * `php artisan db:seed --class='Twstec\Kit\Demo\Database\Seeders\DemoAdminSeeder'`
 * PEDIU aquele super admin. Se o comando terminar com "DONE" sem criar
 * nada, a pessoa acredita que a conta existe — e passa a depender de algo que não está lá. Pior: se ela
 * acredita no contrário (que o comando criou) e o comando de fato criou em
 * outra ocasião, ninguém sabe mais o que há no banco. Falhar alto, com o
 * nome do seeder e o caminho do desbloqueio na mensagem, é a única saída
 * que ensina.
 *
 * O aviso silencioso fica para o AGREGADOR (DemoSeeder), que roda no que um
 * script de deploy roda: ali a recusa precisa ser visível sem derrubar o
 * deploy. Ver DemoSurface e docs/demo.md, "Superfície de demonstração".
 */
final class DemoSurfaceInProductionException extends RuntimeException
{
    /**
     * @param  string  $surface  Nome da peça recusada (classe do seeder, rota, …).
     */
    private function __construct(string $message, public readonly string $surface)
    {
        parent::__construct($message);
    }

    /**
     * Recusa de semeadura: a mensagem carrega o nome do seeder e a variável
     * de ambiente que libera, porque é no console que ela vai ser lida.
     */
    public static function seeder(string $surface): self
    {
        return new self(
            "O seeder [{$surface}] cria dado FICTÍCIO e foi recusado porque APP_ENV=production. "
            .'Isto não é um erro de configuração: é a proteção do kit. Se esta instalação é de '
            .'fato uma demonstração pública hospedada em produção, declare '
            .'DEMO_ALLOW_IN_PRODUCTION=true no ambiente — e assuma que o banco inteiro é '
            .'descartável. Se é uma instalação real, não rode este seeder.',
            surface: $surface,
        );
    }
}
