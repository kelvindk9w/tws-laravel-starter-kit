<?php

declare(strict_types=1);

use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Models\User;
use App\Filament\Resources\Users\Pages\ListUsers;
use Database\Seeders\UserSeeder;
use Livewire\Livewire;

// Lacuna apontada pelo QA: o banco nascia com 2 contas e a paginação/filtros
// do /admin não tinham o que exercitar.

it('semeia pelo menos 40 usuários realistas', function () {
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBeGreaterThanOrEqual(UserSeeder::QUANTIDADE);

    $user = User::query()->latest('id')->first();

    expect($user->name)->not->toBeEmpty()
        ->and($user->email)->toContain('@')
        ->and($user->codigo_publico)->toStartWith('USR-');
});

it('produz variedade para os filtros do admin (bloqueados e não verificados)', function () {
    $this->seed(UserSeeder::class);

    expect(User::query()->where('status', UserStatus::Blocked->value)->count())->toBeGreaterThan(0)
        ->and(User::query()->whereNull('email_verified_at')->count())->toBeGreaterThan(0);
});

it('espalha as datas de criação pelos últimos 90 dias', function () {
    $this->seed(UserSeeder::class);

    $datas = User::query()->pluck('created_at')->map(fn ($d) => $d->toDateString())->unique();

    expect($datas->count())->toBeGreaterThan(10);

    expect(User::query()->where('created_at', '<', now()->subDays(91))->count())->toBe(0);
});

it('é idempotente: rodar de novo não duplica ninguém', function () {
    $this->seed(UserSeeder::class);
    $primeiro = User::query()->count();

    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe($primeiro);
});

it('não toca nas contas demo', function () {
    config()->set('ui.demo_login.email', 'demo@tws.dev');

    $demo = User::factory()->create(['email' => 'demo@tws.dev', 'name' => 'Usuário Demo']);

    $this->seed(UserSeeder::class);

    expect($demo->fresh()->name)->toBe('Usuário Demo');
});

it('a massa semeada faz a paginação do admin ter mais de uma página', function () {
    $this->seed(UserSeeder::class);

    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertOk()
        ->assertCountTableRecords(UserSeeder::QUANTIDADE + 1);
});
