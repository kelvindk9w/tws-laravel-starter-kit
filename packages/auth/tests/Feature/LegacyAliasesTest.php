<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;
use Twstec\Kit\Auth\Contracts\AccountProtection;
use Twstec\Kit\Auth\Enums\UserStatus;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Notifications\ResetPasswordNotification;
use Twstec\Kit\Auth\Services\TwoFactorLogin;

// Nomes antigos (App\Core\Auth\…, da 1.x) continuam resolvendo para as
// classes do pacote — é o que protege payload de fila serializado antes da
// atualização (a notificação de recuperação de senha, o e-mail do código) e
// extensão que ainda implementa o contrato pelo nome antigo.

it('resolve o nome antigo de classe, interface e enum para a classe nova', function (): void {
    expect(class_exists('App\\Core\\Auth\\Services\\TwoFactorLogin'))->toBeTrue()
        ->and((new ReflectionClass('App\\Core\\Auth\\Services\\TwoFactorLogin'))->getName())->toBe(TwoFactorLogin::class)
        ->and(interface_exists('App\\Core\\Auth\\Contracts\\AccountProtection'))->toBeTrue()
        ->and((new ReflectionClass('App\\Core\\Auth\\Contracts\\AccountProtection'))->getName())->toBe(AccountProtection::class)
        ->and(enum_exists('App\\Core\\Auth\\Enums\\UserStatus'))->toBeTrue()
        ->and(constant('App\\Core\\Auth\\Enums\\UserStatus::Blocked'))->toBe(UserStatus::Blocked);
});

it('a notificação e o e-mail serializados com o nome antigo voltam como as classes novas', function (): void {
    $troca = fn (string $serializado, string $novo, string $antigo): string => str_replace(
        sprintf('O:%d:"%s"', strlen($novo), $novo),
        sprintf('O:%d:"%s"', strlen($antigo), $antigo),
        $serializado,
    );

    $notificacao = serialize(new ResetPasswordNotification('token-de-teste'));
    $antiga = $troca($notificacao, ResetPasswordNotification::class, 'App\\Core\\Auth\\Notifications\\ResetPasswordNotification');

    expect($antiga)->not->toBe($notificacao);

    $objeto = unserialize($antiga);

    expect($objeto)->toBeInstanceOf(ResetPasswordNotification::class)
        ->and($objeto->token)->toBe('token-de-teste');

    $mail = serialize(new VerificationCodeMail('123456', VerificationPurpose::LoginChallenge));
    $antigo = $troca($mail, VerificationCodeMail::class, 'App\\Core\\Auth\\Mail\\VerificationCodeMail');

    expect(unserialize($antigo))->toBeInstanceOf(VerificationCodeMail::class);
});

it('não inventa apelido para o que ficou no aplicativo nem fora do módulo', function (): void {
    // O model de usuário e o comando user:make-admin são do aplicativo: quem
    // resolve o nome antigo deles é o aplicativo (o starter tem o apelido).
    expect(class_exists('App\\Core\\Auth\\Models\\User'))->toBeFalse()
        ->and(class_exists('App\\Core\\Auth\\Console\\MakeAdminUser'))->toBeFalse()
        ->and(class_exists('App\\Core\\Auth\\NaoExiste'))->toBeFalse()
        ->and(class_exists('App\\Core\\Tenancy\\TenantContext'))->toBeFalse();
});

it('todo arquivo de src/ tem o nome antigo equivalente resolvível', function (): void {
    $sem = [];

    foreach ((new Finder)->files()->in(dirname(__DIR__, 2).'/src')->name('*.php')->notPath('Compat')->notName('previews.php') as $file) {
        $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists('Twstec\\Kit\\Auth\\'.$relative) && ! interface_exists('Twstec\\Kit\\Auth\\'.$relative) && ! trait_exists('Twstec\\Kit\\Auth\\'.$relative)) {
            continue;
        }

        $antigo = 'App\\Core\\Auth\\'.$relative;

        if (! class_exists($antigo) && ! interface_exists($antigo) && ! trait_exists($antigo)) {
            $sem[] = $antigo;
        }
    }

    expect($sem)->toBe([]);
});
