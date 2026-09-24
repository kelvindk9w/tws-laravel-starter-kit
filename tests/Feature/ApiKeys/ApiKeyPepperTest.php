<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Support\ApiKeyHasher;
use App\Core\ApiKeys\Support\Exceptions\MissingApiKeyPepperException;
use App\Core\ApiKeys\Support\PepperMatch;
use App\Core\ApiKeys\Support\PepperWarnings;
use App\Core\Auth\Models\User;
use App\Core\Tenancy\Middleware\ResolveTenant;
use App\Providers\AppServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

// =============================================================================
// PEPPER VAZIO NUNCA É PEPPER, E TROCAR O PEPPER NÃO INVALIDA AS CHAVES.
//
// `API_KEYS_HASH_PEPPER=` (linha presente, sem valor — era o que o
// .env.example trazia) não aciona o fallback do env(): o HMAC rodava com
// pepper de 0 caracteres, em silêncio. Aqui:
//
//   - vazio ou só espaços vale como ausente → APP_KEY, pelo config/api_keys.php
//     e pelo ApiKeyHasher (que cobre um config antigo da instalação);
//   - peppers anteriores (API_KEYS_PREVIOUS_HASH_PEPPERS) autenticam e o hash
//     é regravado com o atual no primeiro uso;
//   - o pepper vazio legado só é aceito com a flag explícita;
//   - a recusa continua 401 no envelope, contando para o limite de falhas;
//   - produção avisa sobre pepper ausente/vazio e sobre a flag ligada.
// =============================================================================

const ROTA_PEPPER = '/api/v1/projects';

/**
 * Lê o config/api_keys.php com as variáveis dadas no ambiente (como o boot
 * faria, e como o .env as entrega) e devolve o ambiente como estava.
 *
 * @param  array<string, string>  $vars
 * @return array<string, mixed>
 */
function apiKeysConfigWith(array $vars): array
{
    $previous = [];

    foreach ($vars as $name => $value) {
        $previous[$name] = array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null;
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    try {
        return require config_path('api_keys.php');
    } finally {
        foreach ($previous as $name => $value) {
            if ($value === null) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv($name.'='.$value);
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }
    }
}

/**
 * Aplica à configuração em memória o config/api_keys.php lido com as
 * variáveis dadas — o equivalente a subir a aplicação com esse .env.
 *
 * @param  array<string, string>  $vars
 */
function bootApiKeysConfigWith(array $vars): void
{
    config(['api_keys' => apiKeysConfigWith($vars)]);
}

/**
 * Hash gravado da chave, direto do banco (o atributo é oculto no model).
 */
function hashGravado(ApiKey $key): string
{
    return (string) ApiKey::query()->whereKey($key->getKey())->value('secret_hash');
}

/**
 * Recusa no envelope padrão de erro da API, sem detalhe interno.
 */
function assertRecusaPepper(TestResponse $response, int $status, string $code): void
{
    assertErroApi($response, $status, $code);

    expect(array_keys($response->json()))->toBe(['error'])
        ->and(array_keys($response->json('error')))->toBe(['code', 'message', 'correlation_id'])
        ->and($response->getContent())->not->toContain('Exception')
        ->and($response->getContent())->not->toContain('.php');
}

/**
 * Chave como ela ficava gravada antes desta correção, com o pepper vazio.
 */
function chaveComPepperVazioLegado(ApiKey $key, string $secret): ApiKey
{
    ApiKey::query()->whereKey($key->getKey())->update(['secret_hash' => hash_hmac('sha256', $secret, '')]);

    return $key->refresh();
}

/**
 * Trilha de arquivo (request_log) num diretório descartável.
 */
function requestLogDescartavel(): string
{
    $dir = sys_get_temp_dir().'/app-pepper-'.uniqid();

    config()->set('logging.channels.request_log.path', $dir.'/request.log');
    Log::forgetChannel('request_log');

    return $dir;
}

function lerRequestLog(string $dir): string
{
    return implode('', array_map('file_get_contents', glob($dir.'/request-*.log') ?: []));
}

/**
 * Roda o boot do AppServiceProvider como produção e devolve as mensagens
 * de aviso que ele gravou no log.
 *
 * @return list<string>
 */
function avisosDoBootEmProducao(): array
{
    app()->detectEnvironment(fn (): string => 'production');

    $mensagens = [];

    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$mensagens): void {
        if ($event->level === 'warning') {
            $mensagens[] = $event->message;
        }
    });

    (new AppServiceProvider(app()))->boot();

    return $mensagens;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/app-pepper-*') ?: [] as $dir) {
        (new Filesystem)->deleteDirectory($dir);
    }
});

// -----------------------------------------------------------------------------
// Vazio = ausente
// -----------------------------------------------------------------------------

it('pepper vazio ou só com espaços no .env vale como ausente: o hash é o da APP_KEY, nunca o de pepper vazio', function (string $valor): void {
    $secret = 'sk_live_'.str_repeat('z', 48);
    $appKey = (string) env('APP_KEY');

    bootApiKeysConfigWith(['API_KEYS_HASH_PEPPER' => $valor]);

    expect(config('api_keys.hash_pepper'))->toBe($appKey);

    $hasher = app(ApiKeyHasher::class);

    expect($hasher->currentPepper())->toBe($appKey)
        ->and($hasher->hash($secret))->toBe(hash_hmac('sha256', $secret, $appKey))
        ->and($hasher->hash($secret))->not->toBe(hash_hmac('sha256', $secret, ''));
})->with([
    'vazio' => [''],
    'só espaços' => ['   '],
]);

it('um config/api_keys.php antigo que ainda repassa o vazio não vira pepper vazio: o hasher cai na APP_KEY', function (?string $valor): void {
    $secret = 'sk_live_'.str_repeat('y', 48);

    // É o que o config/api_keys.php anterior a esta correção entrega.
    config(['api_keys.hash_pepper' => $valor]);

    $hasher = app(ApiKeyHasher::class);

    expect($hasher->currentPepper())->toBe(config('app.key'))
        ->and($hasher->hash($secret))->toBe(hash_hmac('sha256', $secret, (string) config('app.key')))
        ->and($hasher->hash($secret))->not->toBe(hash_hmac('sha256', $secret, (string) $valor));

    // A chave nova, criada pelo serviço, nasce com o hash da APP_KEY.
    ['api_key' => $key, 'secret_key' => $criada] = criarChave(User::factory()->create());

    expect(hashGravado($key))->toBe(hash_hmac('sha256', $criada, (string) config('app.key')))
        ->and(hashGravado($key))->not->toBe(hash_hmac('sha256', $criada, (string) $valor))
        ->and($hasher->verify($criada, hashGravado($key)))->toBeTrue();
})->with([
    'vazio' => [''],
    'só espaços' => ['  '],
    'nulo' => [null],
]);

it('sem pepper dedicado e sem APP_KEY não há pepper possível: recusa em vez de calcular hash sem segredo', function (): void {
    config(['api_keys.hash_pepper' => '', 'app.key' => '  ']);

    expect(fn () => app(ApiKeyHasher::class)->hash('sk_live_qualquer'))
        ->toThrow(MissingApiKeyPepperException::class);
});

it('o hash fictício da chave pública inexistente usa o pepper normalizado, nunca o vazio', function (): void {
    config(['api_keys.hash_pepper' => '']);

    // A chave pública inexistente continua 401 (e a verificação roda com o
    // pepper da APP_KEY — sem exceção, sem pepper vazio).
    assertRecusaPepper(
        $this->getJson(ROTA_PEPPER, ['X-Api-Key' => 'pk_live_inexistente', 'Authorization' => 'Bearer sk_live_x']),
        401,
        'unauthorized',
    );

    expect(app(ApiKeyHasher::class)->currentPepper())->toBe(config('app.key'));
});

// -----------------------------------------------------------------------------
// Cópia do config (os casos do .env)
// -----------------------------------------------------------------------------

it('pepper dedicado, peppers anteriores e a flag do legado vêm do .env no config/api_keys.php', function (): void {
    $config = apiKeysConfigWith([
        'API_KEYS_HASH_PEPPER' => 'pepper-dedicado-5e1b',
        'API_KEYS_PREVIOUS_HASH_PEPPERS' => 'anterior-1, ,anterior-2',
        'API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY' => 'true',
    ]);

    expect($config['hash_pepper'])->toBe('pepper-dedicado-5e1b')
        ->and($config['previous_peppers'])->toBe(['anterior-1', 'anterior-2'])
        ->and($config['accept_empty_pepper_legacy'])->toBeTrue();
});

it('com o pepper vazio vindo do ambiente, a chave criada no painel nasce com o hash da APP_KEY', function (): void {
    config()->set('api_keys.hash_pepper', '');

    ['api_key' => $key, 'secret_key' => $secret] = criarChave(User::factory()->create());

    $hash = hashGravado($key);

    expect($hash)->toBe(hash_hmac('sha256', $secret, (string) config('app.key')))
        ->and($hash)->not->toBe(hash_hmac('sha256', $secret, ''))
        ->and(app(ApiKeyHasher::class)->verify($secret, $hash))->toBeTrue();
});

// -----------------------------------------------------------------------------
// Peppers anteriores
// -----------------------------------------------------------------------------

it('chave emitida com um pepper anterior autentica, é regravada com o atual e o segundo uso já confere direto', function (): void {
    $dir = requestLogDescartavel();

    config(['api_keys.hash_pepper' => 'pepper-antigo-7f3a']);
    ['api_key' => $key, 'secret_key' => $secret] = criarChave(User::factory()->create());
    expect(hashGravado($key))->toBe(hash_hmac('sha256', $secret, 'pepper-antigo-7f3a'));

    // A instalação troca o pepper e declara o antigo como anterior.
    config([
        'api_keys.hash_pepper' => 'pepper-novo-91cd',
        'api_keys.previous_peppers' => ['pepper-antigo-7f3a'],
    ]);

    $hasher = app(ApiKeyHasher::class);
    expect($hasher->check($secret, hashGravado($key)))->toBe(PepperMatch::Previous);

    // Primeiro uso: autentica e migra.
    $this->getJson(ROTA_PEPPER, headersApi($key, $secret))->assertOk();

    expect(hashGravado($key))->toBe(hash_hmac('sha256', $secret, 'pepper-novo-91cd'))
        ->and($hasher->check($secret, hashGravado($key)))->toBe(PepperMatch::Current);

    // O evento na trilha de arquivo, sem segredo nem hash.
    $log = lerRequestLog($dir);
    expect($log)->toContain(ResolveTenant::HASH_MIGRATED_EVENT)
        ->and($log)->toContain('previous_pepper')
        ->and($log)->toContain((string) $key->uuid)
        ->and($log)->not->toContain($secret)
        ->and($log)->not->toContain('pepper-antigo-7f3a')
        ->and($log)->not->toContain('pepper-novo-91cd')
        ->and($log)->not->toContain(hash_hmac('sha256', $secret, 'pepper-antigo-7f3a'))
        ->and($log)->not->toContain(hash_hmac('sha256', $secret, 'pepper-novo-91cd'));

    // Segundo uso SEM o pepper anterior declarado: confere direto com o atual.
    config(['api_keys.previous_peppers' => []]);
    $this->getJson(ROTA_PEPPER, headersApi($key, $secret))->assertOk();
});

it('a lista de peppers anteriores vem do .env separada por vírgula, sem itens vazios — vazio nunca entra por ela', function (): void {
    bootApiKeysConfigWith(['API_KEYS_PREVIOUS_HASH_PEPPERS' => ' pepper-a , ,pepper-b,,  ']);

    expect(config('api_keys.previous_peppers'))->toBe(['pepper-a', 'pepper-b'])
        ->and(app(ApiKeyHasher::class)->previousPeppers())->toBe(['pepper-a', 'pepper-b'])
        ->and(config('api_keys.accept_empty_pepper_legacy'))->toBeFalse();

    // E uma lista vinda de config com vazios também não aceita hash de pepper vazio.
    config(['api_keys.previous_peppers' => ['', '   ']]);
    $secret = 'sk_live_'.str_repeat('v', 48);

    expect(app(ApiKeyHasher::class)->check($secret, hash_hmac('sha256', $secret, '')))->toBe(PepperMatch::None);
});

it('trocar o pepper sem declarar o anterior continua recusando a chave antiga com 401', function (): void {
    config(['api_keys.hash_pepper' => 'pepper-antigo-7f3a']);
    ['api_key' => $key, 'secret_key' => $secret] = criarChave(User::factory()->create());

    config(['api_keys.hash_pepper' => 'pepper-novo-91cd', 'api_keys.previous_peppers' => []]);

    assertRecusaPepper($this->getJson(ROTA_PEPPER, headersApi($key, $secret)), 401, 'unauthorized');
    expect(hashGravado($key))->toBe(hash_hmac('sha256', $secret, 'pepper-antigo-7f3a'));
});

// -----------------------------------------------------------------------------
// Pepper vazio legado: só com a flag
// -----------------------------------------------------------------------------

it('chave emitida com pepper vazio é recusada sem a flag do legado: 401 no envelope e o hash não muda', function (): void {
    ['api_key' => $key, 'secret_key' => $secret] = criarChave(User::factory()->create());
    $key = chaveComPepperVazioLegado($key, $secret);

    expect(config('api_keys.accept_empty_pepper_legacy'))->toBeFalse()
        ->and(app(ApiKeyHasher::class)->check($secret, hashGravado($key)))->toBe(PepperMatch::None);

    assertRecusaPepper($this->getJson(ROTA_PEPPER, headersApi($key, $secret)), 401, 'unauthorized');

    expect(hashGravado($key))->toBe(hash_hmac('sha256', $secret, ''));
});

it('chave emitida com pepper vazio é aceita com a flag do legado e migrada para o pepper atual no primeiro uso', function (): void {
    $dir = requestLogDescartavel();

    // A flag vem do .env, como numa instalação de verdade (e o pepper vazio
    // também, como no .env copiado do exemplo antigo).
    bootApiKeysConfigWith([
        'API_KEYS_HASH_PEPPER' => '',
        'API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY' => 'true',
    ]);

    expect(config('api_keys.accept_empty_pepper_legacy'))->toBeTrue();

    ['api_key' => $key, 'secret_key' => $secret] = criarChave(User::factory()->create());
    $key = chaveComPepperVazioLegado($key, $secret);

    expect(app(ApiKeyHasher::class)->check($secret, hashGravado($key)))->toBe(PepperMatch::EmptyLegacy);

    $this->getJson(ROTA_PEPPER, headersApi($key, $secret))->assertOk();

    expect(hashGravado($key))->toBe(hash_hmac('sha256', $secret, (string) config('app.key')))
        ->and(hashGravado($key))->not->toBe(hash_hmac('sha256', $secret, ''));

    $log = lerRequestLog($dir);
    expect($log)->toContain(ResolveTenant::HASH_MIGRATED_EVENT)
        ->and($log)->toContain('empty_pepper_legacy')
        ->and($log)->not->toContain($secret);

    // Migrada: com a flag desligada, o segundo uso confere direto.
    config(['api_keys.accept_empty_pepper_legacy' => false]);
    $this->getJson(ROTA_PEPPER, headersApi($key, $secret))->assertOk();
});

it('a flag do legado não abre porta para secreta errada: 401 no envelope e o limite de falhas continua valendo', function (): void {
    config([
        'api_keys.accept_empty_pepper_legacy' => true,
        'api_keys.previous_peppers' => ['pepper-antigo-7f3a'],
        'security.rate_limit.api_auth_failures' => 3,
    ]);

    ['api_key' => $key, 'secret_key' => $secret] = criarChave(User::factory()->create());

    foreach (range(1, 3) as $tentativa) {
        assertRecusaPepper(
            $this->getJson(ROTA_PEPPER, headersApi($key, 'sk_live_errada'.$tentativa)),
            401,
            'unauthorized',
        );
    }

    // Passou do limite: 429 no envelope, e nem a secreta certa passa.
    $response = $this->getJson(ROTA_PEPPER, headersApi($key, $secret));
    assertRecusaPepper($response, 429, 'too_many_requests');
    expect($response->headers->get('Retry-After'))->not->toBeNull();

    // O hash da chave boa não foi tocado por nenhuma tentativa.
    expect(hashGravado($key))->toBe(hash_hmac('sha256', $secret, app(ApiKeyHasher::class)->currentPepper()));
});

it('a verificação calcula todos os peppers aceitos em qualquer caso — mesma quantidade para chave existente e inexistente', function (): void {
    config([
        'api_keys.hash_pepper' => 'pepper-atual',
        'api_keys.previous_peppers' => ['pepper-a', 'pepper-b', 'pepper-atual', 'pepper-a'],
        'api_keys.accept_empty_pepper_legacy' => true,
    ]);

    $hasher = app(ApiKeyHasher::class);
    $secret = 'sk_live_'.str_repeat('t', 48);

    // Sem repetição e sem o atual entre os anteriores.
    expect($hasher->previousPeppers())->toBe(['pepper-a', 'pepper-b']);

    // Cada candidato é reconhecido; o atual vence quando mais de um confere.
    expect($hasher->check($secret, hash_hmac('sha256', $secret, 'pepper-atual')))->toBe(PepperMatch::Current)
        ->and($hasher->check($secret, hash_hmac('sha256', $secret, 'pepper-b')))->toBe(PepperMatch::Previous)
        ->and($hasher->check($secret, hash_hmac('sha256', $secret, '')))->toBe(PepperMatch::EmptyLegacy)
        ->and($hasher->check($secret, hash_hmac('sha256', 'chave-publica-inexistente', 'pepper-atual')))->toBe(PepperMatch::None);

    // Estrutural: o laço dos candidatos não tem saída antecipada, e a
    // comparação é hash_equals.
    $source = (string) file_get_contents((new ReflectionClass(ApiKeyHasher::class))->getFileName());
    preg_match('/function check\(.*?\n    \}\n/s', $source, $check);

    expect($check[0] ?? '')->toContain('hash_equals')
        ->not->toContain('break')
        ->not->toMatch('/foreach.*return \$kind/s');
});

// -----------------------------------------------------------------------------
// Avisos de produção
// -----------------------------------------------------------------------------

it('produção com API_KEYS_HASH_PEPPER vazio: aviso explícito no boot, sem recusar', function (): void {
    bootApiKeysConfigWith(['API_KEYS_HASH_PEPPER' => '']);

    $log = implode("\n", avisosDoBootEmProducao());

    expect($log)->toContain('API_KEYS_HASH_PEPPER está ausente ou VAZIO em APP_ENV=production')
        ->and($log)->toContain('Valor vazio nunca é usado como pepper')
        ->and($log)->not->toContain('API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true');
});

it('produção com a flag do pepper vazio legado ligada: aviso a cada boot, mesmo com pepper dedicado', function (): void {
    bootApiKeysConfigWith(['API_KEYS_HASH_PEPPER' => 'pepper-dedicado-c4e8', 'API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY' => 'true']);

    $primeiro = implode("\n", avisosDoBootEmProducao());
    Event::forget(MessageLogged::class);
    $segundo = implode("\n", avisosDoBootEmProducao());

    foreach ([$primeiro, $segundo] as $log) {
        expect($log)->toContain('API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true em APP_ENV=production')
            ->and($log)->not->toContain('API_KEYS_HASH_PEPPER está ausente ou VAZIO');
    }
});

it('produção com pepper dedicado e sem a flag: nenhum aviso de pepper; fora de produção, nenhum aviso', function (): void {
    bootApiKeysConfigWith(['API_KEYS_HASH_PEPPER' => 'pepper-dedicado-c4e8']);

    $log = implode("\n", avisosDoBootEmProducao());

    expect($log)->not->toContain('API_KEYS_HASH_PEPPER')
        ->and($log)->not->toContain('API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY');

    // Fora de produção o boot não avisa, mas a regra (chamada direto)
    // enxerga as duas condições.
    app()->detectEnvironment(fn (): string => 'testing');
    config(['api_keys.hash_pepper' => '', 'api_keys.accept_empty_pepper_legacy' => true]);
    Event::forget(MessageLogged::class);

    $mensagens = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$mensagens): void {
        $mensagens[] = $event->message;
    });

    (new AppServiceProvider(app()))->boot();

    expect(app()->environment('production'))->toBeFalse()
        ->and(implode("\n", $mensagens))->not->toContain('API_KEYS_')
        ->and(PepperWarnings::forCurrentConfiguration())->toHaveCount(2);
});
