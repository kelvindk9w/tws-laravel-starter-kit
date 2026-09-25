<?php

declare(strict_types=1);

use Twstec\Kit\Auth\Support\LoginPrefill;
use Twstec\Kit\Auth\Support\ProtectedAccounts;
use Twstec\Kit\Demo\Accounts\Exceptions\DemoAccountProtectedException;
use Twstec\Kit\Demo\Support\DemoSurface;
use Twstec\Kit\Demo\Tests\Fixtures\User;
use Twstec\Kit\Foundation\Mail\Contracts\MailPreviewGate;

// =============================================================================
// AS PROTEÇÕES DA DEMONSTRAÇÃO VÊM DO PACOTE — numa aplicação limpa, sem nada
// do starter, instalar a demo basta para:
//
// 1. as contas demo (credenciais públicas) serem intocáveis pelo model, pelo
//    ponto de extensão do produto (AccountProtection);
// 2. a superfície de demonstração FECHAR em produção (fail-closed:
//    DemoSurface), com o opt-out DEMO_ALLOW_IN_PRODUCTION declarado e
//    barulhento (aviso no log a cada boot).
//
// O gatilho do PostgreSQL (a terceira camada das contas demo) é provado na
// suíte do starter, que roda contra PostgreSQL de verdade.
// =============================================================================

/**
 * A conta demo do cliente, gravada como o seeder grava (fora da proteção).
 */
function demoCliente(): User
{
    return User::fixture(['email' => 'demo@tws.dev', 'name' => 'Cliente Demo']);
}

it('com o modo demo ligado, a conta demo é reservada e protegida: não muda e-mail, senha, papel nem status, e não sai', function (): void {
    $this->bootWith(['ui.demo_login.enabled' => true]);

    $demo = demoCliente();

    expect($demo->isReservedAccount())->toBeTrue()
        ->and(ProtectedAccounts::protects($demo))->toBeTrue();

    foreach ([['email' => 'outro@exemplo.com'], ['password' => 'Outra-senha-forte-1'], ['is_admin' => true], ['status' => 'blocked']] as $mudanca) {
        $alterada = User::query()->findOrFail($demo->getKey());

        expect(fn () => $alterada->forceFill($mudanca)->save())->toThrow(DemoAccountProtectedException::class);
    }

    expect(fn () => User::query()->findOrFail($demo->getKey())->delete())->toThrow(DemoAccountProtectedException::class)
        ->and(User::query()->where('email', 'demo@tws.dev')->exists())->toBeTrue();

    // O que não é sensível continua livre (nome, idioma).
    $livre = User::query()->findOrFail($demo->getKey());
    $livre->forceFill(['name' => 'Outro nome'])->save();

    expect($livre->fresh()->name)->toBe('Outro nome');
});

it('uma conta comum não é protegida, e com o modo demo desligado nenhuma é', function (): void {
    $comum = User::fixture(['email' => 'pessoa@exemplo.com']);
    $demo = demoCliente();

    // Modo demo desligado (o padrão fora de `local`): nenhuma conta é protegida.
    expect(ProtectedAccounts::protects($demo))->toBeFalse()
        ->and(ProtectedAccounts::protects($comum))->toBeFalse();

    $demo->delete();

    expect(User::query()->where('email', 'demo@tws.dev')->exists())->toBeFalse();
});

it('em produção a superfície de demonstração não existe, mesmo com as flags ligadas (fail-closed)', function (): void {
    // Sem banco (o `migrate` de produção pediria confirmação): a pergunta de
    // proteção é feita a um usuário com o e-mail demo, não gravado.
    $this->bootWith([
        'app.env' => 'production',
        'ui.demo_login.enabled' => true,
        'ui.showcase_enabled' => true,
    ], migrate: false);

    $demo = new User(['email' => 'demo@tws.dev']);

    expect(DemoSurface::allowed())->toBeFalse()
        ->and(DemoSurface::loginEnabled())->toBeFalse()
        ->and(DemoSurface::showcaseEnabled())->toBeFalse()
        ->and(app(MailPreviewGate::class)->allows())->toBeFalse()
        ->and(LoginPrefill::for('web'))->toBeNull()
        ->and(LoginPrefill::for('admin'))->toBeNull()
        // A conta com o e-mail demo não é blindada em produção: apagá-la de lá
        // tem de continuar possível.
        ->and(ProtectedAccounts::protects($demo))->toBeFalse();

    $this->get('/ui')->assertNotFound();
});

it('em produção, só o opt-out DECLARADO libera a demonstração — e avisa no log a cada boot', function (): void {
    // O aviso sai no boot: o log vai para um arquivo descartável.
    $log = sys_get_temp_dir().'/kit-demo-log-'.uniqid().'.log';

    $this->bootWith([
        'app.env' => 'production',
        'ui.demo_login.enabled' => true,
        'ui.demo.allow_in_production' => true,
        'logging.default' => 'kitdemo',
        'logging.channels.kitdemo' => ['driver' => 'single', 'path' => $log],
    ], migrate: false);

    expect(DemoSurface::allowed())->toBeTrue()
        ->and(DemoSurface::loginEnabled())->toBeTrue()
        ->and(LoginPrefill::for('web')?->email)->toBe('demo@tws.dev');

    expect(is_file($log) ? (string) file_get_contents($log) : '')->toContain('WARNING: DEMO_ALLOW_IN_PRODUCTION está ligado');

    @unlink($log);
});

it('fora de produção, as flags continuam desligando a demo (segunda barreira)', function (): void {
    $this->bootWith(['app.env' => 'local', 'ui.demo_login.enabled' => false, 'ui.showcase_enabled' => false]);

    expect(DemoSurface::allowed())->toBeTrue()
        ->and(DemoSurface::loginEnabled())->toBeFalse()
        ->and(DemoSurface::showcaseEnabled())->toBeFalse()
        ->and(LoginPrefill::for('web'))->toBeNull();

    $this->get('/ui')->assertNotFound();
});
