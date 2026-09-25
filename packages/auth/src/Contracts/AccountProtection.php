<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts;

use Twstec\Kit\Auth\Exceptions\AccountProtectedException;

/**
 * Ponto de extensão: CONTAS PROTEGIDAS CONTRA ALTERAÇÃO.
 *
 * O produto não sabe quais contas são essas nem por quê. Ele só pergunta, nos
 * pontos em que uma alteração de conta acontece (eventos do model, tela do
 * super admin, verificação em duas etapas, verificação de e-mail, comando
 * `user:make-admin`), se alguém registrou uma proteção para a conta em
 * questão. Sem implementação registrada no container, nenhuma conta é
 * protegida e todas as perguntas respondem "não".
 *
 * Quem implementa hoje: a demonstração do kit (twstec/kit-demo), que blinda as contas
 * de credenciais públicas. Quem pergunta: Twstec\Kit\Auth\Support\ProtectedAccounts.
 */
interface AccountProtection
{
    /**
     * A conta é uma das RESERVADAS pela extensão, com a proteção valendo ou
     * não neste momento?
     *
     * É a pergunta da interface do super admin: a conta reservada fica fora
     * das ações de editar, bloquear, excluir e verificar e-mail mesmo quando
     * a proteção das outras camadas está desligada.
     */
    public function reserves(AuthUser $user): bool;

    /**
     * A proteção está valendo AGORA para esta conta?
     *
     * Conta protegida não tem campos sensíveis alterados nem é excluída, e é
     * tratada como de e-mail verificado.
     */
    public function protects(AuthUser $user): bool;

    /**
     * Recusa a alteração em curso (o model está sujo) se ela mexer em algum
     * campo protegido. Não faz nada quando a alteração é permitida.
     *
     * @throws AccountProtectedException
     */
    public function guardUpdate(AuthUser $user): void;

    /**
     * Recusa a exclusão da conta, se ela estiver protegida.
     *
     * @throws AccountProtectedException
     */
    public function guardDelete(AuthUser $user): void;
}
