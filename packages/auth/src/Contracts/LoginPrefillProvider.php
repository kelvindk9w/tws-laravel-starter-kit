<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts;

use Twstec\Kit\Auth\Support\LoginPrefill;

/**
 * Ponto de extensão: CREDENCIAIS SUGERIDAS nas telas de login.
 *
 * As duas telas de login (painel do cliente e `/admin`) perguntam aqui se
 * devem nascer preenchidas. Sem implementação registrada no container, os
 * campos nascem vazios e nenhum aviso aparece.
 *
 * Quem implementa hoje: a demonstração do kit (App\Demo), com as contas de
 * credenciais públicas — e só quando a demonstração pode existir no ambiente.
 */
interface LoginPrefillProvider
{
    /**
     * @param  string  $surface  `web` (login do painel) ou `admin` (login do /admin).
     */
    public function for(string $surface): ?LoginPrefill;
}
