<?php

declare(strict_types=1);

namespace App\Core\Auth\Exceptions;

use RuntimeException;

/**
 * Tentativa de mexer numa CONTA DEMO por um caminho que não é a interface
 * do super admin: tinker, um comando artisan, uma rotina de importação, um
 * job, um teste mal calibrado.
 *
 * A exceção existe (em vez de um `return` silencioso) porque a operação
 * PEDIU uma alteração e ela NÃO aconteceu: engolir isso produz o pior dos
 * mundos — o operador acha que trocou a senha da demo e ela continua a
 * mesma. Falhar alto é o comportamento correto.
 *
 * Quem trata: a UI do Filament nem chega aqui (UserAdminGuard recusa antes,
 * com mensagem amigável) e os comandos do kit convertem a exceção em erro
 * de console legível. Ver README, seção "Contas demo são intocáveis".
 */
final class DemoAccountProtectedException extends RuntimeException
{
    /**
     * @param  list<string>  $attributes  Campos sensíveis que a operação tentou mudar.
     */
    public function __construct(
        string $message,
        public readonly string $operation,
        public readonly ?string $email = null,
        public readonly array $attributes = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $attributes
     */
    public static function update(?string $email, array $attributes): self
    {
        return new self(
            __('auth.demo_account.update_blocked', [
                'email' => (string) $email,
                'fields' => implode(', ', $attributes),
            ]),
            operation: 'update',
            email: $email,
            attributes: $attributes,
        );
    }

    public static function delete(?string $email): self
    {
        return new self(
            __('auth.demo_account.delete_blocked', ['email' => (string) $email]),
            operation: 'delete',
            email: $email,
        );
    }
}
