<?php

declare(strict_types=1);

use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Models\User;
use App\Filament\Resources\Users\Pages\ListUsers;
use Livewire\Livewire;

// Proteção das contas demo (login demo + super admin demo): NÃO podem ser
// bloqueadas/desbloqueadas por ações do super admin — senão um visitante
// quebraria a demonstração para todos os demais. A tentativa é recusada
// com uma notification clara.

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('identifica as contas demo pelas credenciais configuradas', function () {
    $demo = User::factory()->create(['email' => config('ui.demo_login.email')]);
    $demoAdmin = User::factory()->create(['email' => config('ui.demo_admin.email')]);
    $normal = User::factory()->create(['email' => 'pessoa@example.com']);

    expect($demo->isDemo())->toBeTrue()
        ->and($demoAdmin->isDemo())->toBeTrue()
        ->and($normal->isDemo())->toBeFalse();
});

it('recusa bloquear o usuário demo, com notification explicando', function () {
    $demo = User::factory()->create(['email' => config('ui.demo_login.email')]);

    Livewire::test(ListUsers::class)
        ->callTableAction('block', $demo)
        ->assertNotified(__('admin.users.demo_protected'));

    expect($demo->fresh()->status)->toBe(UserStatus::Active);
});

it('recusa bloquear o super admin demo', function () {
    $demoAdmin = User::factory()->create(['email' => config('ui.demo_admin.email')]);

    Livewire::test(ListUsers::class)
        ->callTableAction('block', $demoAdmin)
        ->assertNotified(__('admin.users.demo_protected'));

    expect($demoAdmin->fresh()->status)->toBe(UserStatus::Active);
});

it('recusa desbloquear conta demo (caminho reverso também protegido)', function () {
    $demo = User::factory()->create(['email' => config('ui.demo_login.email')]);
    $demo->forceFill(['status' => UserStatus::Blocked])->save();

    Livewire::test(ListUsers::class)
        ->callTableAction('unblock', $demo)
        ->assertNotified(__('admin.users.demo_protected'));

    expect($demo->fresh()->status)->toBe(UserStatus::Blocked);
});

it('segue bloqueando usuários normais (a guarda não quebra o fluxo)', function () {
    $user = User::factory()->create(['email' => 'cliente@example.com']);

    Livewire::test(ListUsers::class)
        ->callTableAction('block', $user)
        ->assertNotified(__('admin.users.blocked_success'));

    expect($user->fresh()->status)->toBe(UserStatus::Blocked);
});
