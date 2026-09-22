<?php

declare(strict_types=1);

use App\Core\Support\CriticalSecrets;
use App\Core\Support\Exceptions\MissingApplicationKeyException;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Log;

// =============================================================================
// CHAVE DA APLICAÇÃO E SEGREDOS DE PLACEHOLDER — FAIL-CLOSED EM PRODUÇÃO
//
// O achado que originou estes testes: o entrypoint de produção GERAVA uma
// APP_KEY quando ela vinha vazia e a escrevia no `.env` de dentro do container.
// Como a imagem é a mesma para app, migrate, horizon e scheduler (e para cada
// réplica), cada container terminava com uma chave DIFERENTE — e, por morar na
// camada gravável, a chave também não sobrevivia ao restart.
//
// Nada disso dava erro na subida: o cast `encrypted` do nome do usuário
// simplesmente deixava de descriptografar, o cookie de sessão assinado por um
// container era rejeitado pelo outro, e o pepper do hash das chaves de API
// (que tem fallback para a APP_KEY) mudava junto, invalidando toda chave de
// API já emitida. Perda silenciosa de dado é pior que serviço que não sobe.
//
// Aqui se testa a camada da APLICAÇÃO. A camada do shell (o entrypoint que
// aborta com código 78) e a do Compose (senhas sem fallback funcional) são
// provadas pelos comandos registrados no README, seção Produção, porque
// dependem de Docker e a suíte não pode depender dele.
// =============================================================================

/**
 * Chave legítima: 32 bytes aleatórios, o formato que o `key:generate` produz.
 */
function chaveDeVerdade(): string
{
    return 'base64:'.base64_encode(random_bytes(32));
}

/**
 * Finge que esta instalação é de produção — o mesmo sinal que o Laravel usa
 * para HTTPS forçado e que arma todas as proteções de ambiente do kit.
 */
function simulaProducaoDeSegredos(): void
{
    app()->detectEnvironment(fn (): string => 'production');
}

beforeEach(function (): void {
    // Estado de partida saudável: chave própria e nenhum segredo de fachada.
    // Cada teste estraga exatamente uma coisa.
    config()->set('app.key', chaveDeVerdade());
    config()->set('database.connections.pgsql.password', 'senha-longa-e-propria-9f2c');
    config()->set('database.redis.default.password', 'outra-senha-propria-4b71');
    config()->set('backup.backup.password', null);
    config()->set('api_keys.hash_pepper', 'pepper-dedicado-a1b2c3');
    config()->set('filesystems.disks.s3.secret', null);
});

// -----------------------------------------------------------------------------
// Produção SEM chave utilizável: recusa
// -----------------------------------------------------------------------------

it('recusa o boot em produção quando a APP_KEY está ausente', function (): void {
    simulaProducaoDeSegredos();
    config()->set('app.key', null);

    expect(fn () => CriticalSecrets::guard())
        ->toThrow(MissingApplicationKeyException::class);

    expect(CriticalSecrets::applicationKeyUsable())->toBeFalse();
});

it('trata o prefixo base64: solto como ausência de chave', function (): void {
    simulaProducaoDeSegredos();
    config()->set('app.key', 'base64:');

    expect(fn () => CriticalSecrets::guard())
        ->toThrow(MissingApplicationKeyException::class);
});

it('recusa o boot em produção quando a APP_KEY é um valor de placeholder', function (string $chave): void {
    simulaProducaoDeSegredos();
    config()->set('app.key', $chave);

    expect(fn () => CriticalSecrets::guard())
        ->toThrow(MissingApplicationKeyException::class);

    expect(CriticalSecrets::applicationKeyUsable())->toBeFalse();
})->with([
    'vocabulário' => 'troque-esta-chave',
    'vocabulário com prefixo' => 'base64:troque-esta-senha',
    'caixa diferente' => 'CHANGE-ME',
    // "Preenchi com qualquer coisa": 32 bytes nulos. É o placeholder que não
    // tem nome para entrar em lista nenhuma, e por isso é reconhecido pela
    // forma (bytes todos iguais), não pelo valor.
    'degenerado' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
]);

it('aceita placeholder declarado pela própria instalação no vocabulário', function (): void {
    simulaProducaoDeSegredos();
    config()->set('security.secrets.placeholders', ['minha-chave-temporaria']);
    config()->set('app.key', 'minha-chave-temporaria');

    expect(fn () => CriticalSecrets::guard())
        ->toThrow(MissingApplicationKeyException::class);
});

// -----------------------------------------------------------------------------
// Produção COM chave correta, e fora de produção
// -----------------------------------------------------------------------------

it('deixa o boot seguir em produção quando a APP_KEY é própria', function (): void {
    simulaProducaoDeSegredos();

    CriticalSecrets::guard();

    expect(CriticalSecrets::applicationKeyUsable())->toBeTrue();
});

it('não recusa o boot fora de produção, nem sem chave nenhuma', function (): void {
    // Fora de produção a conveniência continua: é onde a chave efêmera não
    // tem dado real a corromper. O guard nem é chamado (o provider só o invoca
    // em produção), e é isso que este teste prova pelo caminho do provider.
    config()->set('app.key', null);

    (new AppServiceProvider(app()))->boot();

    // A chave era de fato inutilizável — e o boot passou mesmo assim.
    expect(CriticalSecrets::applicationKeyUsable())->toBeFalse();
});

it('aplica o guard pelo boot do provider, e não só quando chamado à mão', function (): void {
    simulaProducaoDeSegredos();
    config()->set('app.key', null);

    expect(fn () => (new AppServiceProvider(app()))->boot())
        ->toThrow(MissingApplicationKeyException::class);
});

// -----------------------------------------------------------------------------
// Segredos de infraestrutura: aviso alto, sem recusar
// -----------------------------------------------------------------------------

it('avisa no log, sem recusar o boot, quando um segredo de infraestrutura é placeholder', function (): void {
    simulaProducaoDeSegredos();
    config()->set('database.connections.pgsql.password', 'troque-esta-senha');
    config()->set('database.redis.default.password', 'troque-esta-senha');

    Log::spy();

    // Não recusa: derrubar a aplicação não troca a senha do Postgres. O que
    // ela pode fazer é não deixar ninguém esquecer.
    CriticalSecrets::guard();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $mensagem): bool => str_contains($mensagem, 'DB_PASSWORD')
            && str_contains($mensagem, 'REDIS_PASSWORD'))
        ->once();
});

it('aponta cada segredo crítico pelo nome da variável de ambiente', function (string $caminho, string $variavel): void {
    simulaProducaoDeSegredos();
    config()->set($caminho, 'troque-esta-senha');

    expect(CriticalSecrets::infrastructureSecretsWithPlaceholder())->toContain($variavel);
})->with([
    ['database.connections.pgsql.password', 'DB_PASSWORD'],
    ['database.redis.default.password', 'REDIS_PASSWORD'],
    ['backup.backup.password', 'BACKUP_ARCHIVE_PASSWORD'],
    ['api_keys.hash_pepper', 'API_KEYS_HASH_PEPPER'],
    ['filesystems.disks.s3.secret', 'AWS_SECRET_ACCESS_KEY'],
]);

it('não aponta segredo vazio: ausência é uma decisão possível, valor público não', function (): void {
    simulaProducaoDeSegredos();
    config()->set('backup.backup.password', null);
    config()->set('filesystems.disks.s3.secret', '');

    expect(CriticalSecrets::infrastructureSecretsWithPlaceholder())->toBe([]);
});

it('não avisa nada quando todos os segredos são próprios', function (): void {
    simulaProducaoDeSegredos();

    Log::spy();

    CriticalSecrets::guard();

    Log::shouldNotHaveReceived('warning');
});
