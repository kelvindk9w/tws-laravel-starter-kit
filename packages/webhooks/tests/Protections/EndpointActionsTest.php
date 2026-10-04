<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Account\Models\AccountMembership;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;
use Twstec\Kit\Webhooks\Actions\CreateEndpoint;
use Twstec\Kit\Webhooks\Actions\DeleteEndpoint;
use Twstec\Kit\Webhooks\Actions\SetEndpointStatus;
use Twstec\Kit\Webhooks\Actions\UpdateEndpoint;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;

// =============================================================================
// AS ACTIONS do painel: papel, ação sensível, validação, destino (SSRF) e a
// trilha — de sucesso e de recusa.
// =============================================================================

function deniedAudit(string $action): ?AuditEvent
{
    return AuditEvent::query()->where('action', $action)->where('outcome', 'denied')->latest('id')->first();
}

it('cria o endpoint com a trilha (host, eventos — nunca a URL inteira nem o segredo)', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner, ['url' => 'https://hooks.example.com/webhooks?token=abc', 'events' => ['order.created', 'order.shipped']]);

    $row = AuditEvent::query()->where('action', 'webhook_endpoint.created')->sole();

    expect($endpoint->account_id)->toBe($this->accountOf($owner)->id)
        ->and($endpoint->created_by)->toBe($owner->id)
        ->and($endpoint->events)->toBe(['order.created', 'order.shipped'])
        ->and($endpoint->status)->toBe(EndpointStatus::Active)
        ->and($row->tenant_uuid)->toBe($this->accountOf($owner)->uuid)
        ->and($row->changes['host']['after'])->toBe('hooks.example.com')
        ->and(json_encode($row->changes))->not->toContain('token=abc')
        ->and(json_encode($row->changes))->not->toContain($secret);
});

it('SSRF no cadastro: destino proibido é recusado como erro de validação E registrado na trilha', function (string $url, ?array $dns): void {
    $owner = $this->owner();

    if ($dns !== null) {
        $this->dns->records[$dns[0]] = [$dns[1]];
    }

    $token = $this->sensitiveToken($owner);

    try {
        $this->inAccountOf($owner, fn () => app(CreateEndpoint::class)->handle($owner, ['name' => 'X', 'url' => $url, 'events' => ['*']], $token));
        $this->fail('O cadastro deveria ter sido recusado.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('url');
    }

    $row = deniedAudit('webhook_endpoint.created');

    expect($row)->not->toBeNull()
        ->and($row->reason)->toMatch('/^(private_address|metadata_address)/')
        ->and(Accounts::asSystem('teste', fn () => WebhookEndpoint::query()->count()))->toBe(0);
})->with([
    '127.0.0.1' => ['https://127.0.0.1/hook', null],
    '169.254.169.254' => ['https://169.254.169.254/latest/meta-data/', null],
    '10.0.0.0/8' => ['https://10.20.30.40/hook', null],
    '::1' => ['https://[::1]/hook', null],
    'domínio que resolve para IP privado' => ['https://interno.example.com/hook', ['interno.example.com', '192.168.10.10']],
]);

it('o token de ação sensível só é gasto com o formulário e o destino válidos', function (): void {
    $owner = $this->owner();
    $token = $this->sensitiveToken($owner);

    expect(fn () => $this->inAccountOf($owner, fn () => app(CreateEndpoint::class)->handle($owner, ['name' => 'X', 'url' => 'https://10.0.0.1/', 'events' => ['*']], $token)))
        ->toThrow(ValidationException::class);

    // O mesmo token ainda vale para o envio corrigido.
    $result = $this->inAccountOf($owner, fn () => app(CreateEndpoint::class)->handle($owner, ['name' => 'X', 'url' => 'https://hooks.example.com/', 'events' => ['*']], $token));

    expect($result['endpoint']->events)->toBe(['*']);

    // E não vale duas vezes.
    expect(fn () => $this->inAccountOf($owner, fn () => app(CreateEndpoint::class)->handle($owner, ['name' => 'Y', 'url' => 'https://hooks.example.com/', 'events' => ['*']], $token)))
        ->toThrow(AuthorizationException::class);
});

it('sem ação sensível, não cria — recusa na trilha', function (): void {
    $owner = $this->owner();

    expect(fn () => $this->inAccountOf($owner, fn () => app(CreateEndpoint::class)->handle($owner, ['name' => 'X', 'url' => 'https://hooks.example.com/', 'events' => ['*']], null)))
        ->toThrow(AuthorizationException::class);

    expect(deniedAudit('webhook_endpoint.created')?->reason)->toBe(__('webhooks.errors.sensitive_required'));
});

it('membro não cria; admin cria', function (): void {
    $owner = $this->owner();
    $account = $this->accountOf($owner);
    $member = $this->owner();
    $admin = $this->owner();
    AccountMembership::query()->create(['account_id' => $account->id, 'user_id' => $member->id, 'role' => 'member']);
    AccountMembership::query()->create(['account_id' => $account->id, 'user_id' => $admin->id, 'role' => 'admin']);

    expect(fn () => $this->createEndpoint($member, [], $account))->toThrow(AuthorizationException::class)
        ->and(deniedAudit('webhook_endpoint.created')?->actor_uuid)->toBe($member->uuid);

    expect($this->createEndpoint($admin, [], $account)['endpoint']->account_id)->toBe($account->id);
});

it('valida eventos (catálogo), nome e projeto da conta', function (array $data, string $field): void {
    $owner = $this->owner();
    $token = $this->sensitiveToken($owner);

    try {
        $this->inAccountOf($owner, fn () => app(CreateEndpoint::class)->handle($owner, ['name' => 'X', 'url' => 'https://hooks.example.com/', 'events' => ['order.created'], ...$data], $token));
        $this->fail('Deveria ter recusado.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors())[0])->toStartWith($field);
    }
})->with([
    'evento fora do catálogo' => [['events' => ['invoice.anything']], 'events'],
    'sem eventos' => [['events' => []], 'events'],
    'sem nome' => [['name' => ''], 'name'],
    'projeto inexistente' => [['project' => '0199a1b2-0000-7000-8000-000000000000'], 'project'],
]);

it('projeto de OUTRA conta é recusado como inexistente', function (): void {
    $owner = $this->owner();
    $other = $this->owner();
    $foreign = $this->inAccountOf($other, fn () => Project::query()->create(['name' => 'Alheio']));

    expect(fn () => $this->createEndpoint($owner, ['project' => $foreign->uuid]))->toThrow(ValidationException::class);
});

it('endpoint de outra conta: 404 idêntico ao inexistente, com a tentativa na trilha', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);
    $intruder = $this->owner();

    expect(fn () => $this->inAccountOf($intruder, fn () => app(DeleteEndpoint::class)->handle($intruder, $endpoint->uuid)))
        ->toThrow(ModelNotFoundException::class);

    expect(deniedAudit('webhook_endpoint.deleted')?->subject_uuid)->toBe($endpoint->uuid)
        ->and(Accounts::asSystem('teste', fn () => WebhookEndpoint::query()->count()))->toBe(1);
});

it('altera com ação sensível, revalida o destino e registra o que mudou', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);
    $this->dns->records['novo.example.com'] = ['93.184.215.15'];

    expect(fn () => $this->inAccountOf($owner, fn () => app(UpdateEndpoint::class)->handle($owner, $endpoint->uuid, ['name' => 'Novo', 'url' => 'https://10.0.0.1/', 'events' => ['*']], $this->sensitiveToken($owner))))
        ->toThrow(ValidationException::class);

    $updated = $this->inAccountOf($owner, fn () => app(UpdateEndpoint::class)->handle($owner, $endpoint->uuid, ['name' => 'Novo', 'url' => 'https://novo.example.com/w', 'events' => ['order.shipped']], $this->sensitiveToken($owner)));
    $row = AuditEvent::query()->where('action', 'webhook_endpoint.updated')->where('outcome', 'success')->sole();

    expect($updated->url)->toBe('https://novo.example.com/w')
        ->and($row->changes['host'])->toBe(['before' => 'hooks.example.com', 'after' => 'novo.example.com'])
        ->and($row->changes['events'])->toBe(['before' => ['order.created'], 'after' => ['order.shipped']]);
});

it('desativa e reativa (reativar confere o destino de novo e zera as falhas); exclui', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);
    $endpoint->forceFill(['consecutive_failures' => 7])->save();

    $this->inAccountOf($owner, fn () => app(SetEndpointStatus::class)->handle($owner, $endpoint->uuid, false));
    expect($endpoint->refresh()->status)->toBe(EndpointStatus::Disabled);

    // O nome passou a apontar para a rede interna: não reativa.
    $this->dns->records['hooks.example.com'] = ['10.0.0.8'];
    expect(fn () => $this->inAccountOf($owner, fn () => app(SetEndpointStatus::class)->handle($owner, $endpoint->uuid, true)))->toThrow(ValidationException::class);

    $this->dns->records['hooks.example.com'] = ['93.184.215.14'];
    $this->inAccountOf($owner, fn () => app(SetEndpointStatus::class)->handle($owner, $endpoint->uuid, true));

    expect($endpoint->refresh()->status)->toBe(EndpointStatus::Active)
        ->and($endpoint->consecutive_failures)->toBe(0)
        ->and(AuditEvent::query()->whereIn('action', ['webhook_endpoint.disabled', 'webhook_endpoint.enabled'])->where('outcome', 'success')->count())->toBe(2);

    $this->inAccountOf($owner, fn () => app(DeleteEndpoint::class)->handle($owner, $endpoint->uuid));

    expect(Accounts::asSystem('teste', fn () => WebhookEndpoint::query()->count()))->toBe(0)
        ->and(AuditEvent::query()->where('action', 'webhook_endpoint.deleted')->where('outcome', 'success')->exists())->toBeTrue();
});
