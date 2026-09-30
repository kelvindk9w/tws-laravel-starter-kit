<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup\Contracts;

/**
 * Os processos que o comando único roda: o Composer (o mesmo que está
 * criando o projeto) e o PHP (o artisan do projeto criado). Interface para a
 * suíte trocar por um de mentira — nenhum teste baixa pacote.
 */
interface Runner
{
    /**
     * O Composer, com a saída na tela. Devolve o código de saída.
     *
     * @param  list<string>  $arguments
     * @param  array<string, string>  $env  variáveis acrescentadas ao ambiente
     * @param  bool  $capture  guardar também a saída (para output())
     */
    public function composer(array $arguments, string $cwd, array $env = [], bool $capture = false): int;

    /**
     * A saída guardada da última chamada com $capture (saída e erros juntos).
     */
    public function output(): string;

    /**
     * O PHP em silêncio (só o código de saída importa).
     *
     * @param  list<string>  $arguments
     */
    public function quietPhp(array $arguments, string $cwd): int;
}
