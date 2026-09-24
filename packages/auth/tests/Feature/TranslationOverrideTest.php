<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

// =============================================================================
// O APLICATIVO VENCE O PACOTE NAS TRADUÇÕES — a mesma regra do foundation
// (Twstec\Kit\Foundation\Localization\PackageTranslations), valendo para as
// traduções deste pacote.
//
// Quem usa o kit troca qualquer mensagem editando o lang/ do próprio
// aplicativo: numa mesma chave vale o texto do aplicativo; o pacote só
// preenche o que o aplicativo não definiu — em qualquer grupo, nos três
// idiomas e no idioma de reserva.
// =============================================================================

/**
 * Troca a pasta lang/ do aplicativo por uma temporária com estes arquivos e
 * refaz o carregador de traduções (como num boot novo).
 *
 * @param  array<string, array<string, mixed>>  $files  'pt_BR/auth.php' => conteúdo
 */
function authAppLang(array $files): string
{
    $dir = sys_get_temp_dir().'/auth-app-lang-'.uniqid();

    foreach ($files as $relative => $contents) {
        @mkdir(dirname($dir.'/'.$relative), 0755, true);
        file_put_contents($dir.'/'.$relative, '<?php return '.var_export($contents, true).';');
    }

    app()->useLangPath($dir);
    app()->forgetInstance('translation.loader');
    app()->forgetInstance('translator');

    return $dir;
}

function authPackageLang(string $relative): array
{
    return require dirname(__DIR__, 2).'/lang/'.$relative;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/auth-app-lang-*') ?: [] as $dir) {
        (new Filesystem)->deleteDirectory($dir);
    }
});

it('(a) chave só no pacote: texto do pacote', function (string $locale): void {
    authAppLang(["{$locale}/outro.php" => ['x' => 'y']]);

    expect(__('auth.account_inactive', [], $locale))->toBe(authPackageLang("{$locale}/auth.php")['account_inactive'])
        ->and(__('auth.two_factor.locked', ['minutes' => 3], $locale))
        ->toBe(str_replace(':minutes', '3', authPackageLang("{$locale}/auth.php")['two_factor']['locked']))
        ->and(__('mail.password_reset.subject', ['platform' => 'ACME'], $locale))
        ->toBe(str_replace(':platform', 'ACME', authPackageLang("{$locale}/mail.php")['password_reset']['subject']));
})->with(['pt_BR', 'en', 'es']);

it('(b) mesma chave no aplicativo: texto do aplicativo', function (string $locale): void {
    authAppLang([
        "{$locale}/auth.php" => [
            'failed' => "Falhou no app ({$locale})",
            'two_factor' => ['invalid' => "Código errado no app ({$locale})"],
        ],
        "{$locale}/mail.php" => ['login_code' => ['subject' => "Assunto do app ({$locale})"]],
    ]);

    expect(__('auth.failed', [], $locale))->toBe("Falhou no app ({$locale})")
        ->and(__('auth.two_factor.invalid', [], $locale))->toBe("Código errado no app ({$locale})")
        ->and(__('mail.login_code.subject', [], $locale))->toBe("Assunto do app ({$locale})");
})->with(['pt_BR', 'en', 'es']);

it('(c) grupo repartido: cada chave resolve do lado certo — app, auth e foundation no mesmo grupo', function (string $locale): void {
    authAppLang([
        "{$locale}/auth.php" => [
            // A tela é do app; a mensagem do fluxo é do pacote.
            'two_factor' => ['title' => "Título do app ({$locale})", 'cancelled' => "Cancelado no app ({$locale})"],
            'ui' => ['login_title' => "Entrar no app ({$locale})"],
        ],
        "{$locale}/mail.php" => [
            'footer' => ['cnpj' => "CNPJ do app ({$locale})"],
            'password_reset' => ['title' => "Título do e-mail do app ({$locale})"],
        ],
    ]);

    $auth = authPackageLang("{$locale}/auth.php");
    $mail = authPackageLang("{$locale}/mail.php");
    $foundationMail = require dirname(__DIR__, 2).'/vendor/twstec/kit-foundation/lang/'.$locale.'/mail.php';

    expect(__('auth.two_factor.title', [], $locale))->toBe("Título do app ({$locale})")
        ->and(__('auth.two_factor.cancelled', [], $locale))->toBe("Cancelado no app ({$locale})")
        ->and(__('auth.two_factor.expired', [], $locale))->toBe($auth['two_factor']['expired'])
        ->and(__('auth.ui.login_title', [], $locale))->toBe("Entrar no app ({$locale})")
        // mail.php: rodapé do app sobre o do foundation, assunto do auth, corpo do app.
        ->and(__('mail.footer.cnpj', [], $locale))->toBe("CNPJ do app ({$locale})")
        ->and(__('mail.footer.transactional', [], $locale))->toBe($foundationMail['footer']['transactional'])
        ->and(__('mail.password_reset.subject', [], $locale))->toBe($mail['password_reset']['subject'])
        ->and(__('mail.password_reset.title', [], $locale))->toBe("Título do e-mail do app ({$locale})");
})->with(['pt_BR', 'en', 'es']);

it('no idioma de reserva o aplicativo também vence, e o pacote preenche o idioma pedido', function (): void {
    authAppLang(['pt_BR/auth.php' => ['account_inactive' => 'Inativa pelo app (reserva)']]);

    app('translator')->setFallback('pt_BR');

    expect(__('auth.account_inactive', [], 'fr'))->toBe('Inativa pelo app (reserva)')
        ->and(__('auth.account_inactive', [], 'en'))->toBe(authPackageLang('en/auth.php')['account_inactive']);
});

it('põe a pasta do pacote antes da do aplicativo no carregador, junto com a do foundation', function (): void {
    $dir = authAppLang(['pt_BR/outro.php' => ['x' => 'y']]);

    // O tradutor resolvido é o que a aplicação usa de verdade (qualquer
    // gancho registrado nele também vale).
    app('translator');

    $paths = app('translation.loader')->paths();
    $auth = realpath(dirname(__DIR__, 2).'/lang');
    $foundation = realpath(dirname(__DIR__, 2).'/vendor/twstec/kit-foundation/lang');
    $posicao = fn (string $path): int|false => array_search($path, array_map(fn (string $p): string => realpath($p) ?: $p, $paths), true);

    expect($posicao($dir))->toBe(count($paths) - 1)
        ->and($posicao($auth))->toBeInt()
        ->and($posicao($foundation))->toBeInt()
        ->and($posicao($auth))->toBeLessThan($posicao($dir))
        ->and($posicao($foundation))->toBeLessThan($posicao($dir));
});
