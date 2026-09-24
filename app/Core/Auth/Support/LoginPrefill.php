<?php

declare(strict_types=1);

namespace App\Core\Auth\Support;

use App\Core\Auth\Contracts\LoginPrefillProvider;

/**
 * Credenciais com que uma tela de login nasce preenchida, e o aviso que a
 * tela mostra sobre elas (ver App\Core\Auth\Contracts\LoginPrefillProvider).
 */
final readonly class LoginPrefill
{
    /**
     * @param  string|null  $notice  Aviso exibido acima do formulário (null = sem aviso).
     * @param  string|null  $label  Rótulo das credenciais impressas no aviso.
     */
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
        public ?string $notice = null,
        public ?string $label = null,
    ) {}

    /**
     * Preenchimento sugerido para a tela, ou null quando nenhuma extensão
     * registrada sugere credenciais (o caso de uma instalação sem demo).
     *
     * @param  string  $surface  `web` ou `admin`.
     */
    public static function for(string $surface): ?self
    {
        if (! app()->bound(LoginPrefillProvider::class)) {
            return null;
        }

        return app(LoginPrefillProvider::class)->for($surface);
    }
}
