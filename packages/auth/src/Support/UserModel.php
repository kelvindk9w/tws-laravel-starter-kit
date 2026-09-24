<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * O model de usuário DA APLICAÇÃO, visto de dentro do pacote.
 *
 * O pacote nunca nomeia a classe do usuário: lê a que a aplicação configurou
 * para o guard de sessão (`auth.providers.users.model`, o mesmo lugar que o
 * framework lê) e confere que ela é um model Eloquent que implementa
 * Contracts\AuthUser. Configuração errada falha alto, na primeira consulta,
 * com a explicação — nunca um login que "não acha ninguém".
 */
final class UserModel
{
    /**
     * @return class-string<Model&AuthUser>
     */
    public static function name(): string
    {
        $class = config('auth.providers.users.model');

        if (! is_string($class) || ! class_exists($class)) {
            throw new LogicException('twstec/kit-auth: auth.providers.users.model não aponta para uma classe existente. Configure o model de usuário da aplicação (ex.: App\\Models\\User).');
        }

        if (! is_subclass_of($class, Model::class) || ! is_subclass_of($class, AuthUser::class)) {
            throw new LogicException(sprintf(
                'twstec/kit-auth: o model de usuário %s precisa ser um model Eloquent que implementa %s (use a trait %s).',
                $class,
                AuthUser::class,
                'Twstec\\Kit\\Auth\\Models\\Concerns\\KitAuthenticatable',
            ));
        }

        return $class;
    }

    /**
     * @return Builder<Model&AuthUser>
     */
    public static function query(): Builder
    {
        return self::name()::query();
    }

    /**
     * Instância nova, não salva.
     */
    public static function make(): Model&AuthUser
    {
        $class = self::name();

        return new $class;
    }
}
