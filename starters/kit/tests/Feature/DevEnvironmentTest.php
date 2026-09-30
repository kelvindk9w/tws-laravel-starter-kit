<?php

declare(strict_types=1);

use Twstec\Kit\Setup\Choice;
use Twstec\Kit\Setup\Dev\DevEnvironment;
use Twstec\Kit\Setup\Dev\DockerHost;
use Twstec\Kit\Setup\Tests\Fixtures\FakeHost;

// =============================================================================
// O DOCKER DE DESENVOLVIMENTO do projeto criado: o NOME (COMPOSE_PROJECT_NAME,
// o host <nome>.localhost, o banco, o cookie) e o NÚMERO que define as quatro
// portas (site 808N, e-mails 802N, Vite 803N, banco 804N). A máquina é de
// mentira (FakeHost): projetos Docker que "existem", portas que o Docker "já
// publicou" e portas que um programa fora do Docker "ocupa".
// =============================================================================

it('as quatro portas de um número terminam nele; depois do 9, a centena seguinte com o mesmo padrão', function (int $slot, array $ports): void {
    expect(array_values(DevEnvironment::ports($slot)))->toBe($ports);
})->with([
    '0' => [0, [8080, 8020, 8030, 8040]],
    '3' => [3, [8083, 8023, 8033, 8043]],
    '9' => [9, [8089, 8029, 8039, 8049]],
    '10 (centena seguinte)' => [10, [8180, 8120, 8130, 8140]],
    '17' => [17, [8187, 8127, 8137, 8147]],
    '99 (o último)' => [99, [8989, 8929, 8939, 8949]],
]);

it('o número escrito: só de 0 a 99, com espaços tolerados', function (string $text, ?int $slot): void {
    expect(DevEnvironment::parseSlot($text))->toBe($slot);
})->with([
    ['0', 0], [' 7 ', 7], ['12', 12], ['99', 99],
    ['100', null], ['-1', null], ['a', null], ['', null], ['1.5', null],
]);

it('o texto vira nome válido: minúsculas, sem acento, hífen, começando por letra', function (string $text, string $slug): void {
    expect(DevEnvironment::slug($text))->toBe($slug)
        ->and(DevEnvironment::nameProblem($slug))->toBeNull();
})->with([
    ['Loja da Maria!', 'loja-da-maria'],
    ['  Ação & Cia  ', 'acao-cia'],
    ['meu_projeto', 'meu-projeto'],
    ['2024 Painel', 'painel'],
    ['CAFÉ--PÃO', 'cafe-pao'],
]);

it('o nome fora do padrão ou do tamanho é recusado', function (string $name, string $problem): void {
    expect(DevEnvironment::nameProblem($name))->toBe($problem);
})->with([
    ['Loja', 'format'],
    ['loja_da', 'format'],
    ['1loja', 'format'],
    ['loja-', 'format'],
    ['a', 'length'],
    [str_repeat('a', 41), 'length'],
]);

it('o nome sugerido: o da pasta; o do ZIP do twstec-kit vira meu-projeto; sem colidir com projeto que existe', function (string $folder, array $projects, string $name): void {
    expect(DevEnvironment::suggestName($folder, $projects))->toBe($name);
})->with([
    'pasta com nome' => ['Loja da Maria', [], 'loja-da-maria'],
    'ZIP do GitHub' => ['twstec-kit-main', [], 'meu-projeto'],
    'clone' => ['twstec-kit', [], 'meu-projeto'],
    'pasta do compose (sublinhado)' => ['twstec-kit_main', [], 'meu-projeto'],
    'colide' => ['loja', ['loja'], 'loja-2'],
    'colide duas vezes' => ['twstec-kit-main', ['meu-projeto', 'meu-projeto-2'], 'meu-projeto-3'],
    'pasta sem letra' => ['2024', [], 'meu-projeto'],
]);

it('o banco e o cookie têm o nome do projeto (sublinhado no lugar do hífen)', function (): void {
    $env = DevEnvironment::environment('loja-da-maria', 2, false, 1000, 1000, false, fn (): string => 'segredo');

    expect($env['COMPOSE_PROJECT_NAME'])->toBe('loja-da-maria')
        ->and($env['DB_DATABASE'])->toBe('loja_da_maria')
        ->and($env['SESSION_COOKIE'])->toBe('loja_da_maria_session')
        ->and($env['APP_URL'])->toBe('http://loja-da-maria.localhost:8082')
        ->and($env['PLATFORM_OFFICIAL_URL'])->toBe('http://loja-da-maria.localhost:8082')
        ->and([$env['DEV_SITE_PORT'], $env['DEV_MAIL_PORT'], $env['DEV_VITE_PORT'], $env['DEV_DB_PORT']])->toBe(['8082', '8022', '8032', '8042'])
        ->and($env['COMPOSE_PROFILES'])->toBe('')
        ->and($env['DB_HOST'])->toBe('postgres')
        ->and($env['REDIS_HOST'])->toBe('redis')
        ->and($env['MAIL_HOST'])->toBe('mailpit');
});

it('as senhas do banco e do Redis são geradas por projeto (nunca a mesma, nunca a do .env.example)', function (): void {
    $secret = fn (): string => bin2hex(random_bytes(16));
    $a = DevEnvironment::environment('a-a', 0, false, 1000, 1000, false, $secret);
    $b = DevEnvironment::environment('b-b', 1, false, 1000, 1000, false, $secret);

    expect($a['DB_PASSWORD'])->not->toBe($a['REDIS_PASSWORD'])
        ->and($a['DB_PASSWORD'])->not->toBe($b['DB_PASSWORD'])
        ->and($a['DB_PASSWORD'])->not->toBe('tws_dev_password')
        ->and(strlen($a['DB_PASSWORD']))->toBe(32);
});

it('o banco só é publicado se pedido (o perfil db-port); o dono dos arquivos é o da máquina, nunca root', function (): void {
    $env = DevEnvironment::environment('loja', 0, true, 0, 0, true, fn (): string => 'x');

    expect($env['COMPOSE_PROFILES'])->toBe('db-port')
        ->and($env['DEV_UID'])->toBe('1000')
        ->and($env['DEV_GID'])->toBe('1000')
        ->and($env['DEV_VITE_POLLING'])->toBe('true')
        ->and(DevEnvironment::environment('loja', 0, false, 1001, 1002, false, fn (): string => 'x'))->toMatchArray(['DEV_UID' => '1001', 'DEV_GID' => '1002']);
});

it('máquina vazia: o número sugerido é o 0', function (): void {
    expect(DevEnvironment::firstFree(new FakeHost))->toBe(0);
});

it('o número sugerido é o primeiro com as QUATRO portas livres ao mesmo tempo', function (): void {
    $host = (new FakeHost)->withProject('loja-da-maria', 0);
    // No número 1, só o Vite (8031) está ocupado, por um programa fora do
    // Docker: o 1 não serve.
    $host->busy = [8031];

    expect(DevEnvironment::firstFree($host))->toBe(2)
        ->and(DevEnvironment::occupants($host, 0))->toBe([8020 => 'loja-da-maria', 8030 => 'loja-da-maria', 8080 => 'loja-da-maria'])
        ->and(DevEnvironment::occupants($host, 1))->toBe([8031 => ''])
        ->and(DevEnvironment::occupants($host, 2))->toBe([]);
});

it('cada porta conta sozinha: o banco (804N) ocupado também tira o número', function (): void {
    $host = new FakeHost(busy: [8040]);

    expect(DevEnvironment::occupants($host, 0))->toBe([8040 => ''])
        ->and(DevEnvironment::firstFree($host))->toBe(1);
});

it('o que o Docker já sabe não é testado de novo abrindo porta', function (): void {
    $host = (new FakeHost)->withProject('loja', 0);

    DevEnvironment::occupants($host, 0);

    // Só a porta do banco (não publicada) foi testada.
    expect($host->probes)->toBe([[8040]]);
});

it('0 a 9 ocupados: segue para a centena seguinte, no mesmo padrão (10 = 8180, 8120, 8130, 8140)', function (): void {
    $host = new FakeHost;

    for ($slot = 0; $slot <= 9; $slot++) {
        $host->withProject("projeto-{$slot}", $slot);
    }

    expect(DevEnvironment::firstFree($host))->toBe(10)
        ->and(DevEnvironment::ports(10))->toBe(['site' => 8180, 'mail' => 8120, 'vite' => 8130, 'database' => 8140]);

    // E com o 10 também ocupado (o dev do monorepo na 8180), o 11 — se livre.
    $host->published[8180] = 'tws-laravel-starter-kit';
    $host->published[8181] = 'tws-laravel-starter-kit';

    expect(DevEnvironment::firstFree($host))->toBe(12);
});

it('nenhum número livre até o 99: null (o menu e o ambiente dizem o que fazer)', function (): void {
    $busy = [];

    for ($slot = 0; $slot <= 99; $slot++) {
        $busy[] = DevEnvironment::ports($slot)['site'];
    }

    expect(DevEnvironment::firstFree(new FakeHost(busy: $busy)))->toBeNull();
});

it('os números de uma centena que o Docker já usa, com o dono (para o menu dizer "0 já é usado por…")', function (): void {
    $host = (new FakeHost)->withProject('loja-da-maria', 0)->withProject('painel', 3);
    $host->published[8033] = 'imobv2';

    expect(DevEnvironment::usedInHundred($host, 0))->toBe([0 => ['loja-da-maria'], 3 => ['painel', 'imobv2']]);
});

// --- pelo ambiente (sem perguntas) ------------------------------------------

it('sem variável: o nome da pasta e o primeiro número livre', function (): void {
    [$choice, $error] = Choice::fromEnvironment([], ['livewire', 'react'], 'livewire', translator(), (new FakeHost)->withProject('meu-app', 0), 'meu-app');

    expect($error)->toBeNull()
        ->and($choice->name)->toBe('meu-app-2')
        ->and($choice->slot)->toBe(1)
        ->and($choice->exposeDatabase)->toBeFalse();
});

it('TWS_KIT_NAME, TWS_KIT_SLOT e TWS_KIT_EXPOSE_DB valem quando livres', function (): void {
    [$choice, $error] = envChoice(['TWS_KIT_NAME' => 'loja', 'TWS_KIT_SLOT' => '7', 'TWS_KIT_EXPOSE_DB' => '1']);

    expect($error)->toBeNull()
        ->and([$choice->name, $choice->slot, $choice->exposeDatabase])->toBe(['loja', 7, true]);
});

it('TWS_KIT_NAME de um projeto Docker que já existe é RECUSADO, com outro nome sugerido', function (): void {
    [$choice, $error] = envChoice(['TWS_KIT_NAME' => 'loja-da-maria'], (new FakeHost)->withProject('loja-da-maria', 0));

    expect($choice)->toBeNull()
        ->and($error)->toBe('In TWS_KIT_NAME: there is already a Docker project called loja-da-maria on this machine (containers or volumes, even stopped). Use another name — suggestion: loja-da-maria-2.');
});

it('TWS_KIT_NAME fora do padrão é recusado, com o nome válido sugerido', function (): void {
    [$choice, $error] = envChoice(['TWS_KIT_NAME' => 'Loja da Maria']);

    expect($choice)->toBeNull()
        ->and($error)->toBe('In TWS_KIT_NAME: the name "Loja da Maria" does not work: use lowercase letters, digits and hyphen, starting with a letter (suggestion: loja-da-maria).');
});

it('TWS_KIT_SLOT com UMA das portas ocupada é RECUSADO: quem ocupa e o primeiro livre', function (): void {
    $host = (new FakeHost)->withProject('loja-da-maria', 0);
    $host->busy = [8031];

    [$choice, $error] = envChoice(['TWS_KIT_SLOT' => '1'], $host);

    expect($choice)->toBeNull()
        ->and($error)->toBe('In TWS_KIT_SLOT: number 1 is in use — another program (8031). The first number with all four ports free is 2.');

    [, $error] = envChoice(['TWS_KIT_SLOT' => '0'], $host);

    expect($error)->toContain('loja-da-maria (8020, 8030, 8080)');
});

it('TWS_KIT_SLOT e TWS_KIT_EXPOSE_DB inválidos são recusados', function (array $env, string $message): void {
    [$choice, $error] = envChoice($env);

    expect($choice)->toBeNull()->and($error)->toContain($message);
})->with([
    [['TWS_KIT_SLOT' => '100'], 'invalid project number: 100'],
    [['TWS_KIT_SLOT' => 'dois'], 'invalid project number: dois'],
    [['TWS_KIT_EXPOSE_DB' => 'talvez'], 'Invalid value in TWS_KIT_EXPOSE_DB: talvez'],
]);

it('sem número livre de 0 a 99: recusa com o que fazer', function (): void {
    $busy = [];

    for ($slot = 0; $slot <= 99; $slot++) {
        $busy[] = DevEnvironment::ports($slot)['mail'];
    }

    [$choice, $error] = envChoice([], new FakeHost(busy: $busy));

    expect($choice)->toBeNull()->and($error)->toContain('No number from 0 to 99 has all four ports free');
});

// --- a máquina de verdade (sem Docker: a rede desta máquina) ----------------

it('fora do container, a porta em uso é vista tentando abri-la', function (): void {
    $server = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) stream_socket_get_name($server, false), strrpos((string) stream_socket_get_name($server, false), ':') + 1);

    try {
        expect((new DockerHost(docker: '/caminho/que/nao/existe'))->busy([$port]))->toBe([$port]);
    } finally {
        fclose($server);
    }

    expect((new DockerHost(docker: '/caminho/que/nao/existe'))->busy([$port]))->toBe([]);
});

it('sem o Docker: nada de projetos nem portas conhecidas; no container, nenhuma porta pode ser conferida', function (): void {
    $host = new DockerHost(inContainer: true, probeImage: 'qualquer', docker: '/caminho/que/nao/existe');

    expect($host->available())->toBeFalse()
        ->and($host->projects())->toBe([])
        ->and($host->published())->toBe([])
        ->and($host->busy([8080]))->toBe([]);
});

it('o que o Docker diz: portas de containers rodando e PARADOS (com o projeto dono), projetos de volumes e redes, e os números reservados', function (): void {
    $bin = temporaryDirectory();
    $log = $bin.'/chamadas.log';
    $inspect = json_encode([
        [
            'Name' => '/loja-nginx-1',
            'Config' => ['Labels' => ['com.docker.compose.project' => 'loja']],
            'HostConfig' => ['PortBindings' => ['80/tcp' => [['HostIp' => '127.0.0.1', 'HostPort' => '8080']]]],
            'NetworkSettings' => ['Ports' => []],
        ],
        [
            // Parado: sem portas na rede, mas com as configuradas.
            'Name' => '/painel-mailpit-1',
            'Config' => ['Labels' => ['com.docker.compose.project' => 'painel']],
            'HostConfig' => ['PortBindings' => ['8025/tcp' => [['HostIp' => '127.0.0.1', 'HostPort' => '8021']]]],
            'NetworkSettings' => ['Ports' => null],
        ],
        [
            // Um container avulso (sem Compose): o nome dele.
            'Name' => '/avulso',
            'Config' => ['Labels' => []],
            'HostConfig' => ['PortBindings' => ['80/tcp' => [['HostPort' => '8033']]]],
            'NetworkSettings' => ['Ports' => []],
        ],
        [
            // O teste de porta do próprio instalador não conta.
            'Name' => '/twstec-kit-probe-1',
            'Config' => ['Labels' => ['twstec.kit.probe' => '1']],
            'HostConfig' => ['PortBindings' => ['8084/tcp' => [['HostPort' => '8084']]]],
            'NetworkSettings' => ['Ports' => []],
        ],
    ]);
    file_put_contents($bin.'/inspect.json', $inspect);
    file_put_contents($bin.'/docker', <<<SH
        #!/bin/sh
        echo "\$*" >> {$log}
        case "\$1 \$2" in
          "ps -aq") printf 'a\\nb\\nc\\nd\\n' ;;
          "inspect a") cat {$bin}/inspect.json ;;
          "volume ls")
            case "\$*" in
              *twstec.kit.slot*) echo 'recem-criado 5' ;;
              *) printf 'loja\\nsó-volume\\n' ;;
            esac ;;
          "network ls") echo 'so-rede' ;;
          "volume create") ;;
          *) exit 1 ;;
        esac
        SH);
    chmod($bin.'/docker', 0755);

    try {
        $host = new DockerHost(docker: $bin.'/docker');

        expect($host->available())->toBeTrue()
            ->and($host->projects())->toBe(['loja', 'painel', 'so-rede', 'só-volume'])
            ->and($host->published())->toBe([
                8021 => 'painel',
                8025 => 'recem-criado',
                8033 => 'avulso',
                8035 => 'recem-criado',
                8045 => 'recem-criado',
                8080 => 'loja',
                8085 => 'recem-criado',
            ])
            ->and(DevEnvironment::occupants($host, 5))->toBe([8025 => 'recem-criado', 8035 => 'recem-criado', 8045 => 'recem-criado', 8085 => 'recem-criado'])
            ->and(DevEnvironment::occupants($host, 3))->toBe([8033 => 'avulso']);

        // A reserva: o volume do banco do projeto, com os rótulos do Compose
        // e o do número.
        $host->reserve('nova-loja', 7);

        expect((string) file_get_contents($log))->toContain('volume create --label com.docker.compose.project=nova-loja --label com.docker.compose.volume=postgres_data --label twstec.kit.slot=7 nova-loja_postgres_data');
    } finally {
        removeDirectory($bin);
    }
})->skip(PHP_OS_FAMILY === 'Windows', 'o docker de mentira é um script sh');

// --- o próprio instalador não conta (nome do projeto = nome da pasta) --------

/**
 * Um `docker` de mentira com estes containers (o `inspect`) e estes projetos
 * em volumes; devolve o DockerHost que o usa e a pasta (para apagar).
 *
 * @return array{0: DockerHost, 1: string}
 */
function fakeDocker(array $containers, array $volumeProjects = []): array
{
    $bin = temporaryDirectory();
    file_put_contents($bin.'/inspect.json', json_encode($containers));
    file_put_contents($bin.'/volumes.txt', implode("\n", $volumeProjects)."\n");
    $ids = implode('\\n', array_map(fn (int $i): string => "c{$i}", array_keys($containers)));
    file_put_contents($bin.'/docker', <<<SH
        #!/bin/sh
        case "\$1 \$2" in
          "ps -aq") printf '{$ids}\\n' ;;
          inspect*) cat {$bin}/inspect.json ;;
          "volume ls") case "\$*" in *twstec.kit.slot*) ;; *) cat {$bin}/volumes.txt ;; esac ;;
          "network ls") ;;
          *) exit 1 ;;
        esac
        SH);
    chmod($bin.'/docker', 0755);

    return [new DockerHost(docker: $bin.'/docker'), $bin];
}

/**
 * O container do `docker compose run --rm instalar` numa pasta `loja-teste`:
 * o Compose o põe no projeto `loja-teste`, serviço `instalar`, de uma vez.
 */
function installerContainer(string $project = 'loja-teste'): array
{
    return [
        'Name' => "/{$project}-instalar-run-3f2a9c1b7d4e",
        'Config' => ['Labels' => [
            'com.docker.compose.project' => $project,
            'com.docker.compose.service' => 'instalar',
            'com.docker.compose.oneoff' => 'True',
        ]],
        'HostConfig' => ['PortBindings' => []],
        'NetworkSettings' => ['Ports' => []],
    ];
}

it('O CENÁRIO DO GUIA: pasta loja-teste, instalador rodando no projeto loja-teste — o nome da pasta é sugerido e ACEITO', function (): void {
    [$host, $bin] = fakeDocker([installerContainer()]);

    try {
        expect($host->projects())->toBe([])
            ->and($host->published())->toBe([]);

        // Pelo ambiente, com o nome = o da pasta (o passo do teste real).
        [$choice, $error] = Choice::fromEnvironment(['TWS_KIT_NAME' => 'loja-teste'], ['livewire', 'react'], 'livewire', translator(), $host, 'loja-teste');

        expect($error)->toBeNull()
            ->and($choice->name)->toBe('loja-teste');

        // E sem nome: a sugestão é o da pasta, sem "-2".
        [$choice] = Choice::fromEnvironment([], ['livewire', 'react'], 'livewire', translator(), $host, 'loja-teste');

        expect($choice->name)->toBe('loja-teste');
    } finally {
        removeDirectory($bin);
    }
})->skip(PHP_OS_FAMILY === 'Windows', 'o docker de mentira é um script sh');

it('com um projeto DE VERDADE com o mesmo nome (container do app, ou só o volume do banco), continua recusando', function (array $containers, array $volumes): void {
    [$host, $bin] = fakeDocker([installerContainer(), ...$containers], $volumes);

    try {
        [$choice, $error] = Choice::fromEnvironment(['TWS_KIT_NAME' => 'loja-teste'], ['livewire', 'react'], 'livewire', translator(), $host, 'loja-teste');

        expect($choice)->toBeNull()
            ->and($error)->toContain('there is already a Docker project called loja-teste')
            ->toContain('suggestion: loja-teste-2');
    } finally {
        removeDirectory($bin);
    }
})->with([
    'o app parado' => [[[
        'Name' => '/loja-teste-app-1',
        'Config' => ['Labels' => ['com.docker.compose.project' => 'loja-teste', 'com.docker.compose.service' => 'app']],
        'HostConfig' => ['PortBindings' => []],
        'NetworkSettings' => ['Ports' => []],
    ]], []],
    'só o volume do banco' => [[], ['loja-teste']],
    // Um `run` de outro serviço (não é o instalador) conta.
    'run de outro serviço' => [[[
        'Name' => '/loja-teste-app-run-1',
        'Config' => ['Labels' => ['com.docker.compose.project' => 'loja-teste', 'com.docker.compose.service' => 'app', 'com.docker.compose.oneoff' => 'True']],
        'HostConfig' => ['PortBindings' => []],
        'NetworkSettings' => ['Ports' => []],
    ]], []],
])->skip(PHP_OS_FAMILY === 'Windows', 'o docker de mentira é um script sh');

it('o instalador não conta nas portas (mesmo se publicasse): os números continuam livres', function (): void {
    $installer = installerContainer();
    $installer['HostConfig']['PortBindings'] = ['80/tcp' => [['HostPort' => '8080']]];
    [$host, $bin] = fakeDocker([$installer]);

    try {
        expect($host->published())->toBe([])
            ->and(DevEnvironment::usedInHundred($host, 0))->toBe([]);
    } finally {
        removeDirectory($bin);
    }
})->skip(PHP_OS_FAMILY === 'Windows', 'o docker de mentira é um script sh');
