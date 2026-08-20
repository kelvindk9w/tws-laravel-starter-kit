<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Core\Auth\Mail\VerificationCodeMail;
use App\Core\Auth\Models\User;
use Illuminate\Support\Facades\Mail;

// =============================================================================
// Helpers compartilhados da suíte de API Keys/Tenancy (Fase 4).
// Arquivo sem testes — apenas funções usadas pelos demais arquivos da suíte.
// =============================================================================

/**
 * Cria uma chave de API real via service (o caminho de produção) e retorna
 * a chave + a secreta em claro (que só existe neste momento — ADR-006).
 *
 * @param  array{name?: string, scopes?: list<string>|null, expires_at?: string|null, project_uuids?: list<string>|null}  $data
 * @return array{api_key: ApiKey, secret_key: string}
 */
function criarChave(User $user, array $data = []): array
{
    return app(ApiKeyService::class)->create($user, ['name' => 'Chave de teste', ...$data]);
}

/**
 * Headers de autenticação da API (ADR-010): pk_ no X-Api-Key + sk_ no Bearer.
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
 * Emite um token de ação sensível pelo FLUXO REAL da Fase 3 (senha de
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
