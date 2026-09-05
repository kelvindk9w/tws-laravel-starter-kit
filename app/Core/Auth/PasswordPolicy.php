<?php

declare(strict_types=1);

namespace App\Core\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * Política da senha de LOGIN, num lugar só (ADR-007: nada hardcoded).
 *
 * Todo ponto que valida senha de login — registro público, reset por
 * e-mail, troca no perfil e criação/edição de usuário no /admin — chama
 * PasswordPolicy::rule(). Ligar ou desligar uma exigência é uma linha no
 * .env (ver config/auth.php → password_rules); o kit nasce com o mínimo
 * (só tamanho) e as demais regras prontas para ativar.
 *
 * A senha de TRANSAÇÃO tem política própria (auth.transaction_password) e
 * não passa por aqui.
 */
final class PasswordPolicy
{
    public static function rule(): Password
    {
        $config = (array) config('auth.password_rules', []);

        $rule = Password::min((int) ($config['min_length'] ?? 6));

        if ($config['letters'] ?? false) {
            $rule->letters();
        }

        if ($config['mixed_case'] ?? false) {
            $rule->mixedCase();
        }

        if ($config['numbers'] ?? false) {
            $rule->numbers();
        }

        if ($config['symbols'] ?? false) {
            $rule->symbols();
        }

        if ($config['uncompromised'] ?? false) {
            $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * Frase para dicas de formulário, montada a partir do que está ATIVO —
     * a dica nunca promete uma regra que a validação não cobra (nem o
     * contrário). Ex.: "mínimo de 6 caracteres" ou "mínimo de 12
     * caracteres, com maiúscula e minúscula, número e símbolo".
     */
    public static function hint(): string
    {
        $config = (array) config('auth.password_rules', []);

        $partes = [];

        foreach (['letters', 'mixed_case', 'numbers', 'symbols'] as $regra) {
            if ($config[$regra] ?? false) {
                $partes[] = __("auth.password_policy.{$regra}");
            }
        }

        $minimo = __('auth.password_policy.min', ['min' => (int) ($config['min_length'] ?? 6)]);

        if ($partes === []) {
            return $minimo;
        }

        return __('auth.password_policy.with', [
            'min' => $minimo,
            'rules' => implode(__('auth.password_policy.separator'), $partes),
        ]);
    }
}
