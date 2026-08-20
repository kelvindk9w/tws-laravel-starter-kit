<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Livewire\Notifications\Preferences;
use Livewire\Livewire;

// =============================================================================
// Preferências de notificação (Livewire — Fase 6): esqueleto de toggles de
// e-mail preparado para as notificações de pagamento do gatPay (ADR-009).
// =============================================================================

it('exige autenticação (deny-by-default)', function () {
    $this->get('/notifications')->assertRedirect(route('login'));
});

it('renderiza os toggles do catálogo com os defaults do config', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Preferences::class)
        ->assertOk()
        ->assertSee(__('panel.notifications.pref_payment_confirmed'))
        ->assertSee(__('panel.notifications.pref_security_alerts'))
        ->assertSee(__('panel.notifications.locked'))
        ->assertSet('preferences.payment_confirmed', true);
});

it('salva as preferências no JSON do usuário', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Preferences::class)
        ->set('preferences.payment_confirmed', false)
        ->set('preferences.final_customer_receipt', false)
        ->call('save');

    $user->refresh();

    expect($user->notificationPreference('payment_confirmed'))->toBeFalse()
        ->and($user->notificationPreference('final_customer_receipt'))->toBeFalse()
        ->and($user->notificationPreference('api_key_events'))->toBeTrue();
});

it('toggle travado (alertas de segurança) é SEMPRE gravado como ativo', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Preferences::class)
        ->set('preferences.security_alerts', false) // tentativa de desligar
        ->call('save');

    expect($user->fresh()->notificationPreference('security_alerts'))->toBeTrue();
});
