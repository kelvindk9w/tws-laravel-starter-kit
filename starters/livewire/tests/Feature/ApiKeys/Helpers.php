<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;

// =============================================================================
// Helpers compartilhados da suíte de API Keys/Tenancy.
// Arquivo sem testes — apenas funções usadas pelos demais arquivos da suíte.
// =============================================================================

/**
 * Cria uma chave de API real via service (o caminho de produção) e retorna
 * a chave + a secreta em claro (que só existe neste momento).
 *
 * @param  array{name?: string, scopes?: list<string>|null, expires_at?: string|null, project_uuids?: list<string>|null}  $data
 * @return array{api_key: ApiKey, secret_key: string}
 */
function criarChave(User $user, array $data = []): array
{
    return app(ApiKeyService::class)->create($user, ['name' => 'Chave de teste', ...$data]);
}

/**
 * Headers de autenticação da API: pk_ no X-Api-Key + sk_ no Bearer.
 *
 * @return array<string, string>
 */
function headersApi(ApiKey $apiKey, string $secretKey): array
{
    return [
        'X-Api-Key' => $apiKey->public_key,
        'Authorization' => 'Bearer '.$secretKey,
    ];
}

/**
 * Emite um token de ação sensível pelo FLUXO REAL da ação sensível (senha de
 * transação + código de 6 dígitos capturado do e-mail com Mail::fake).
 * Exige usuário criado com withTransactionPassword() e Mail::fake() ativo.
 */
function tokenAcaoSensivel(User $user): string
{
    test()->actingAs($user)->postJson('/sensitive-actions/code', [
        'transaction_password' => 'Trans4cao!Segura',
    ])->assertOk();

    $codigo = null;

    Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$codigo): bool {
        $codigo = $mail->code;

        return true;
    });

    $response = test()->actingAs($user)->postJson('/sensitive-actions/confirm', ['code' => $codigo]);

    $response->assertOk();

    return (string) $response->json('token');
}

/**
 * Envelope de ERRO da API (ver ApiErrorRenderer e docs/api.md):
 * {"error": {"code", "message", "correlation_id"}}, com "errors" em 422.
 *
 * Estes helpers existem para que a suíte inteira afirme o MESMO contrato:
 * se o envelope mudar, muda em um lugar só.
 */
function assertErroApi(TestResponse $response, int $status, string $code): TestResponse
{
    return $response->assertStatus($status)
        ->assertJsonStructure(['error' => ['code', 'message', 'correlation_id']])
        ->assertJsonPath('error.code', $code);
}

/**
 * 422 com erro no campo informado (a chave pode ter ponto — "scopes.0" —,
 * por isso a checagem é por chave literal, não por caminho aninhado).
 */
function assertErroDeValidacaoApi(TestResponse $response, string $campo): TestResponse
{
    assertErroApi($response, 422, 'validation_failed');

    /** @var array<string, mixed> $errors */
    $errors = $response->json('error.errors') ?? [];

    expect($errors)->toHaveKey($campo);

    return $response;
}
