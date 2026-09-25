<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

// =============================================================================
// TRADUÇÕES DA DEMONSTRAÇÃO — a regra de todo pacote do kit: O APLICATIVO
// VENCE (Twstec\Kit\Foundation\Localization\PackageTranslations). E uma a
// mais, própria da demo: nas mensagens NEUTRAS do produto sobre conta
// protegida (twstec/kit-admin e twstec/kit-auth), vale o texto "de demo" dela
// — com a demonstração instalada, a conta protegida é a conta demo.
// =============================================================================

/**
 * Troca a pasta lang/ do aplicativo por uma temporária com estes arquivos e
 * refaz o carregador de traduções (como num boot novo).
 *
 * @param  array<string, array<string, mixed>>  $files  'pt_BR/landing.php' => conteúdo
 */
function demoAppLang(array $files): string
{
    $dir = sys_get_temp_dir().'/demo-app-lang-'.uniqid();

    foreach ($files as $relative => $contents) {
        @mkdir(dirname($dir.'/'.$relative), 0755, true);
        file_put_contents($dir.'/'.$relative, '<?php return '.var_export($contents, true).';');
    }

    app()->useLangPath($dir);
    app()->forgetInstance('translation.loader');
    app()->forgetInstance('translator');

    return $dir;
}

/**
 * @return array<string, mixed>
 */
function demoPackageLang(string $relative): array
{
    return require dirname(__DIR__, 2).'/lang/'.$relative;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/demo-app-lang-*') ?: [] as $dir) {
        (new Filesystem)->deleteDirectory($dir);
    }
});

it('chave só no pacote: texto do pacote', function (string $locale): void {
    demoAppLang(["{$locale}/outro.php" => ['x' => 'y']]);

    $landing = demoPackageLang("{$locale}/landing.php");
    $contato = demoPackageLang("{$locale}/contact.php");

    expect(__('landing.hero.title_line_1', [], $locale))->toBe($landing['hero']['title_line_1'])
        ->and(__('contact.heading', [], $locale))->toBe($contato['heading']);
})->with(['pt_BR', 'en', 'es']);

it('mesma chave no aplicativo: texto do aplicativo', function (string $locale): void {
    demoAppLang([
        "{$locale}/landing.php" => ['hero' => ['title_line_1' => "Título do app ({$locale})"]],
        "{$locale}/admin.php" => ['users' => ['account_protected' => "Protegida pelo app ({$locale})"]],
    ]);

    $landing = demoPackageLang("{$locale}/landing.php");

    expect(__('landing.hero.title_line_1', [], $locale))->toBe("Título do app ({$locale})")
        // O resto do grupo continua vindo do pacote.
        ->and(__('landing.hero.title_line_2', [], $locale))->toBe($landing['hero']['title_line_2'])
        ->and(__('admin.users.account_protected', [], $locale))->toBe("Protegida pelo app ({$locale})");
})->with(['pt_BR', 'en', 'es']);

it('dá o texto de demo às mensagens neutras de conta protegida do produto (e o nome antigo acompanha)', function (string $locale): void {
    $admin = demoPackageLang("{$locale}/admin.php");
    $auth = demoPackageLang("{$locale}/auth.php");
    $neutroAdmin = require dirname(__DIR__, 2).'/vendor/twstec/kit-admin/lang/'.$locale.'/admin.php';
    $neutroAuth = require dirname(__DIR__, 2).'/vendor/twstec/kit-auth/lang/'.$locale.'/auth.php';

    expect(__('admin.users.account_protected', [], $locale))->toBe($admin['users']['account_protected'])
        ->and(__('admin.users.demo_protected', [], $locale))->toBe($admin['users']['account_protected'])
        ->and(__('admin.command.account_protected', ['email' => 'x'], $locale))->toBe(str_replace(':email', 'x', $admin['command']['account_protected']))
        ->and(__('admin.profile.email_readonly_note', [], $locale))->toBe($admin['profile']['email_readonly_note'])
        ->and(__('auth.two_factor.account_protected', [], $locale))->toBe($auth['two_factor']['account_protected'])
        ->and(__('auth.two_factor.demo_blocked', [], $locale))->toBe($auth['two_factor']['account_protected'])
        // E o texto do produto, sem a demo, é outro (neutro).
        ->and($admin['users']['account_protected'])->not->toBe($neutroAdmin['users']['account_protected'])
        ->and($auth['two_factor']['account_protected'])->not->toBe($neutroAuth['two_factor']['account_protected']);
})->with(['pt_BR', 'en', 'es']);
