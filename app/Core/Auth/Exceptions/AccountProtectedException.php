<?php

declare(strict_types=1);

namespace App\Core\Auth\Exceptions;

use RuntimeException;

/**
 * Uma alteração foi recusada porque a conta está PROTEGIDA (ver
 * App\Core\Auth\Contracts\AccountProtection).
 *
 * É a base que o produto conhece: quem trata a recusa (o comando
 * `user:make-admin`, por exemplo) captura esta classe. A extensão que protege
 * as contas lança uma subclasse com a sua própria mensagem.
 *
 * Existe como exceção, e não como `return` silencioso, porque a operação
 * PEDIU uma alteração e ela não aconteceu: engolir isso faria o operador
 * acreditar numa mudança que não houve.
 */
class AccountProtectedException extends RuntimeException {}
