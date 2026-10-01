<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Support;

/**
 * O cadastro público está aberto nesta instalação?
 * (`AUTH_REGISTRATION_ENABLED`, padrão `true`)
 *
 * Fechado, o registro deixa de existir para quem está de fora: a tela e o
 * envio respondem 404 (o mesmo de uma rota que não existe — não anuncia que
 * a instalação tem cadastro desligado) e as telas não oferecem o link. Conta
 * nova passa a nascer só por convite (twstec/kit-accounts) ou pelo /admin —
 * caminhos que não passam por aqui.
 *
 * Quem confere: o controller do envio (RegisteredUserController) e a própria
 * Action (RegisterUser), para que nenhum front reabra o cadastro chamando a
 * regra direto; a tela do front confere com ensureOpen() e esconde os links
 * com enabled().
 */
final class Registration
{
    public static function enabled(): bool
    {
        $value = config('auth.registration.enabled', true);

        if ($value === null || is_bool($value)) {
            return $value ?? true;
        }

        // `false`, "false", "0", "off", "no" fecham; texto que não é
        // booleano deixa como está (aberto).
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * Cadastro fechado → 404.
     */
    public static function ensureOpen(): void
    {
        abort_unless(self::enabled(), 404);
    }
}
