<?php

declare(strict_types=1);

use Twstec\Kit\Setup\Choice;
use Twstec\Kit\Setup\CreateProject;
use Twstec\Kit\Setup\Output;
use Twstec\Kit\Setup\Tests\Fixtures\FakeHost;
use Twstec\Kit\Setup\Tests\Fixtures\FakeRunner;

// =============================================================================
// O COMANDO ÚNICO de ponta a ponta, sem baixar nada: a pasta do projeto é a
// de um `composer create-project twstec/kit` recém-baixado (o composer.json
// deste pacote, o vendor/, a pasta kit-setup/) e o Composer é de mentira —
// o `create-project --no-install` dele escreve um starter falso.
// =============================================================================

beforeEach(function (): void {
    $this->project = temporaryDirectory();
    $this->runner = new FakeRunner;
    $this->host = new FakeHost;
    $this->stream = fopen('php://memory', 'w+');

    $kit = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
    // A versão publicada (prepare-composer.php troca o 2.x-dev do monorepo).
    $kit['extra']['twstec-kit']['constraint'] = '^2.0@beta';
    $this->writeKit = function (array $changes = []) use ($kit): void {
        file_put_contents($this->project.'/composer.json', json_encode(array_replace_recursive($kit, $changes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    };
    ($this->writeKit)();

    file_put_contents($this->project.'/README.md', "# twstec/kit\n");
    file_put_contents($this->project.'/LICENSE', "MIT (kit)\n");
    file_put_contents($this->project.'/SECURITY.md', "kit\n");
    mkdir($this->project.'/kit-setup/src', 0777, true);
    file_put_contents($this->project.'/kit-setup/create.php', "<?php\n");
    mkdir($this->project.'/vendor');
    file_put_contents($this->project.'/vendor/autoload.php', "<?php\n");

    // A pasta, como a pessoa a vê (a pasta temporária tem nome aleatório):
    // a base do nome sugerido do projeto.
    // O sistema (Linux; 'Windows' simula o PHP do Windows) e o comando de quem
    // chamou (as opções do create-project).
    $this->create = function (array $env = [], ?Closure $menu = null, string $locale = 'en', string $os = 'Linux', array $caller = []): int {
        return (new CreateProject($this->project, translator($locale), $this->runner, new Output($this->stream, false), ['TWS_KIT_FOLDER' => 'meu-app', ...$env], $this->host, $menu, $os, $caller))->run();
    };

    $this->output = function (): string {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    };

    $this->composerJson = fn (): array => json_decode((string) file_get_contents($this->project.'/composer.json'), true);
});

afterEach(function (): void {
    removeDirectory($this->project);
});

it('sem perguntas e sem escolha: Livewire com todos os módulos, na ordem do create-project do starter', function (): void {
    expect(($this->create)())->toBe(0);

    $steps = $this->runner->composerSteps();

    expect($steps[0])->toStartWith('create-project twstec/starter-livewire:^2.0@beta '.$this->project.'/.tws-kit-starter-')
        ->and($steps[0])->toEndWith('--no-install --no-scripts --no-interaction')
        ->and(array_slice($steps, 1))->toBe([
            'run-script post-root-package-install --no-interaction',
            'update --no-interaction',
            'run-script post-create-project-cmd --no-interaction',
        ]);

    $json = ($this->composerJson)();

    expect($json['name'])->toBe('twstec/starter-livewire')
        ->and(array_keys($json['require']))->toContain('twstec/kit-accounts', 'twstec/kit-uploads', 'twstec/kit-admin', 'twstec/kit-foundation', 'twstec/kit-auth')
        // Objeto vazio continua objeto (o composer.json segue válido).
        ->and((string) file_get_contents($this->project.'/composer.json'))->toContain('"allow-plugins": {}');
});

it('troca os arquivos do kit pelos do starter: nada do kit sobra, nem a pasta temporária', function (): void {
    ($this->create)();

    $entries = array_values(array_diff(scandir($this->project), ['.', '..']));
    sort($entries);

    expect($entries)->toBe(['.env', '.env.example', '.gitignore', 'LICENSE', 'README.md', 'app', 'artisan', 'composer.json', 'lang', 'vendor'])
        ->and(file_get_contents($this->project.'/README.md'))->toBe("# twstec/starter-livewire\n")
        ->and(file_get_contents($this->project.'/LICENSE'))->toBe("MIT (starter)\n")
        // O vendor/ fica: o `composer update` o reaproveita.
        ->and(file_exists($this->project.'/vendor/autoload.php'))->toBeTrue();
});

it('React sem uploads pelo ambiente: o starter React, sem o pacote desmarcado, e o instalador recebe a escolha', function (): void {
    expect(($this->create)(['TWS_KIT_STACK' => 'react', 'TWS_KIT_WITHOUT' => 'uploads']))->toBe(0);

    $json = ($this->composerJson)();

    expect($json['name'])->toBe('twstec/starter-react')
        ->and($json['require'])->not->toHaveKey('twstec/kit-uploads')
        ->and($json['require'])->toHaveKeys(['twstec/kit-accounts', 'twstec/kit-admin', 'twstec/kit-foundation', 'twstec/kit-auth'])
        // O instalador (require-dev) fica: ele gera as chaves e roda o banco.
        ->and($json['require-dev'])->toHaveKey('twstec/kit-installer');

    $installer = array_values(array_filter($this->runner->calls, fn (array $call): bool => ($call[1][1] ?? '') === 'post-create-project-cmd'))[0];

    expect($installer[2])->toBe([
        'TWS_KIT_WITH' => 'accounts,admin',
        'TWS_KIT_WITHOUT' => 'uploads',
        'APP_LOCALE' => 'en',
        'TWS_KIT_FROM_KIT' => '1',
        // O Docker de desenvolvimento: o nome da pasta e o primeiro número
        // livre, sem o banco publicado.
        'TWS_KIT_NAME' => 'meu-app',
        'TWS_KIT_SLOT' => '0',
        'TWS_KIT_EXPOSE_DB' => '0',
    ])
        ->and(($this->output)())->toContain('Choice (no questions): project meu-app, number 0; React');
});

it('só a base: nenhum módulo opcional no composer.json', function (): void {
    expect(($this->create)(['TWS_KIT_WITHOUT' => 'accounts,uploads,admin']))->toBe(0);

    $require = ($this->composerJson)()['require'];

    foreach (['twstec/kit-accounts', 'twstec/kit-uploads', 'twstec/kit-admin'] as $package) {
        expect($require)->not->toHaveKey($package);
    }

    expect($require)->toHaveKeys(['twstec/kit-foundation', 'twstec/kit-auth']);
});

it('escolha inválida no ambiente para ANTES de baixar qualquer coisa — o projeto fica como estava', function (array $env, string $message): void {
    $before = file_get_contents($this->project.'/composer.json');

    expect(($this->create)($env))->toBe(1)
        ->and($this->runner->calls)->toBe([])
        ->and(file_get_contents($this->project.'/composer.json'))->toBe($before)
        ->and(is_dir($this->project.'/kit-setup'))->toBeTrue()
        ->and(($this->output)())->toContain($message)->toContain('Nothing was installed');
})->with([
    'uploads sem contas' => [['TWS_KIT_WITHOUT' => 'accounts'], 'Secure uploads and profile photo (twstec/kit-uploads) needs Accounts'],
    'interface desconhecida' => [['TWS_KIT_STACK' => 'vue'], 'Unknown interface in TWS_KIT_STACK: vue'],
    'módulo desconhecido' => [['TWS_KIT_WITH' => 'billing'], 'Unknown module in TWS_KIT_WITH: billing'],
    'obrigatório de fora' => [['TWS_KIT_WITHOUT' => 'auth'], 'The auth module is always included'],
    'nas duas listas' => [['TWS_KIT_WITH' => 'admin', 'TWS_KIT_WITHOUT' => 'admin'], 'is in both TWS_KIT_WITH and TWS_KIT_WITHOUT'],
]);

it('o menu: a escolha dele vale; desistir não baixa nada', function (): void {
    $asked = [];
    $menu = function (array $starters, string $default) use (&$asked): Choice {
        $asked = [$starters, $default];

        return new Choice('react', ['admin']);
    };

    expect(($this->create)([], $menu))->toBe(0)
        ->and($asked)->toBe([['livewire' => 'twstec/starter-livewire', 'react' => 'twstec/starter-react'], 'livewire'])
        ->and(($this->composerJson)()['name'])->toBe('twstec/starter-react')
        ->and(($this->composerJson)()['require'])->not->toHaveKeys(['twstec/kit-accounts', 'twstec/kit-uploads']);

    removeDirectory($this->project.'/composer.json');
    ($this->writeKit)();
    $this->runner->calls = [];

    expect(($this->create)([], fn (): ?Choice => null))->toBe(1)
        ->and($this->runner->calls)->toBe([])
        ->and(($this->output)())->toContain('Nothing was installed');
});

it('falha ao baixar o starter: a pasta temporária sai, o projeto fica como estava e a mensagem diz o que fazer', function (): void {
    $this->runner->failing['create-project'] = 1;

    expect(($this->create)())->toBe(1)
        ->and(glob($this->project.'/.tws-kit-starter-*'))->toBe([])
        ->and(json_decode((string) file_get_contents($this->project.'/composer.json'), true)['name'])->toBe('twstec/kit')
        ->and(($this->output)())->toContain('Could not download the twstec/starter-livewire starter')
        ->toContain('delete the '.$this->project.' folder');

    expect(array_slice($this->runner->composerSteps(), 1))->toBe([]);
});

it('pacote baixado que não é o starter esperado: recusado, nada muda', function (): void {
    $this->runner->downloadedName = 'alguem/outro-projeto';

    expect(($this->create)())->toBe(1)
        ->and(glob($this->project.'/.tws-kit-starter-*'))->toBe([])
        ->and(json_decode((string) file_get_contents($this->project.'/composer.json'), true)['name'])->toBe('twstec/kit')
        ->and(($this->output)())->toContain('is not the expected starter');
});

it('falha no composer update: para, diz o passo e os comandos exatos para terminar, e o código do kit sai', function (): void {
    $this->runner->failing['update'] = 1;

    expect(($this->create)())->toBe(1)
        ->and(end($this->runner->calls)[1])->toBe(['update', '--no-interaction'])
        ->and(is_dir($this->project.'/kit-setup'))->toBeFalse();

    $output = ($this->output)();

    expect($output)->toContain('the "Installing the dependencies (composer update)" step failed')
        ->toContain("    cd {$this->project}\n    composer update\n    composer run-script post-create-project-cmd\n")
        ->toContain('Or delete the '.$this->project.' folder');
});

it('falha no instalador do starter: só falta ele', function (): void {
    $this->runner->failing['run-script post-create-project-cmd'] = 1;

    expect(($this->create)())->toBe(1);

    expect(($this->output)())->toContain("    cd {$this->project}\n    composer run-script post-create-project-cmd\n")
        ->not->toContain('    composer update');
});

it('repositórios do create-project (--add-repository): acham o starter a partir do projeto e ficam no composer.json dele', function (): void {
    ($this->writeKit)(['repositories' => [['type' => 'artifact', 'url' => '../packages']]]);

    ($this->create)();

    $download = $this->runner->calls[0][1];
    $repositories = array_values(array_filter($download, fn (string $a): bool => str_starts_with($a, '--repository=')));

    expect($repositories)->toBe([
        '--repository={"type":"artifact","url":"'.$this->project.'/../packages"}',
        '--repository={"type":"composer","url":"https://repo.packagist.org"}',
    ])
        // No projeto, o endereço relativo de quem chamou, como o
        // --add-repository o deixaria (e o build da imagem o usa).
        ->and(($this->composerJson)()['repositories'])->toBe([['type' => 'artifact', 'url' => '../packages']]);
});

it('sem repositórios próprios: o Packagist de sempre, sem --repository', function (): void {
    ($this->create)();

    expect(array_filter($this->runner->calls[0][1], fn (string $a): bool => str_starts_with($a, '--repository=')))->toBe([])
        ->and(($this->composerJson)())->not->toHaveKey('repositories');
});

it('banco inacessível: a instrução exata, com o que está no .env', function (): void {
    $this->runner->databaseStatus = 1;

    expect(($this->create)())->toBe(0)
        ->and(($this->output)())->toContain('The .env database (DB_CONNECTION=pgsql, DB_HOST=postgres) did not respond')
        ->toContain('    php artisan migrate');

    expect(end($this->runner->calls))->toBe(['php', ['artisan', 'migrate:status', '--pending=1', '--no-interaction'], [], $this->project]);
});

it('banco pronto: diz que as migrations rodaram, e os próximos passos', function (): void {
    ($this->create)();

    expect(($this->output)())->toContain('Database ready: migrations applied.')
        ->toContain('Project ready in '.$this->project)
        ->toContain('    npm install && npm run build');
});

it('o instalador do projeto fala a língua do menu', function (): void {
    ($this->create)([], null, 'es');

    $installer = array_values(array_filter($this->runner->calls, fn (array $call): bool => ($call[1][1] ?? '') === 'post-create-project-cmd'))[0];

    expect($installer[2]['APP_LOCALE'])->toBe('es')
        ->and(($this->output)())->toContain('Proyecto listo en');
});

it('composer.json do kit sem a configuração extra.twstec-kit: recusa sem mexer em nada', function (): void {
    file_put_contents($this->project.'/composer.json', json_encode(['name' => 'twstec/kit']));

    expect(($this->create)())->toBe(1)
        ->and($this->runner->calls)->toBe([]);
});

it('a pasta do instalador que não sai (arquivo preso): o projeto fica pronto, com o aviso para apagá-la', function (): void {
    $setup = $this->project.'/kit-setup';
    chmod($setup, 0555);

    try {
        expect(($this->create)())->toBe(0)
            ->and(($this->output)())->toContain('Could not delete '.$setup.' (file in use?)');
    } finally {
        chmod($setup, 0755);
    }
})->skip(function_exists('posix_getuid') && posix_getuid() === 0, 'como root, a permissão não impede apagar');

// --- o Docker de desenvolvimento do projeto ----------------------------------

it('número (TWS_KIT_SLOT) com porta ocupada: recusado ANTES de baixar qualquer coisa', function (): void {
    $this->host->withProject('loja-da-maria', 0);

    expect(($this->create)(['TWS_KIT_SLOT' => '0']))->toBe(1)
        ->and($this->runner->calls)->toBe([])
        ->and(($this->output)())->toContain('number 0 is in use — loja-da-maria')->toContain('Nothing was installed');
});

it('nome (TWS_KIT_NAME) de um projeto Docker que já existe: recusado ANTES de baixar qualquer coisa', function (): void {
    $this->host->withProject('loja', 3);

    expect(($this->create)(['TWS_KIT_NAME' => 'loja']))->toBe(1)
        ->and($this->runner->calls)->toBe([])
        ->and(($this->output)())->toContain('there is already a Docker project called loja')->toContain('suggestion: loja-2');
});

it('sem variável, com outro projeto no 0: o nome da pasta e o número 1 vão para o instalador', function (): void {
    $this->host->withProject('outro', 0);

    ($this->create)(['TWS_KIT_EXPOSE_DB' => '1']);

    $installer = array_values(array_filter($this->runner->calls, fn (array $call): bool => ($call[1][1] ?? '') === 'post-create-project-cmd'))[0];

    expect($installer[2])->toMatchArray(['TWS_KIT_NAME' => 'meu-app', 'TWS_KIT_SLOT' => '1', 'TWS_KIT_EXPOSE_DB' => '1'])
        ->and(($this->output)())->toContain('Choice (no questions): project meu-app, number 1;');
});

it('starter com o Docker de desenvolvimento (compose.yaml): o resumo dá o endereço, os e-mails e o docker compose up -d — sem conferir banco da máquina', function (): void {
    $this->runner->withCompose = true;

    expect(($this->create)(['TWS_KIT_SLOT' => '4']))->toBe(0);

    expect(($this->output)())->toContain('The project database runs in Docker')
        ->toContain('Development Docker: project meu-app, number 4')
        ->toContain('Site: http://meu-app.localhost:8084')
        ->toContain('Sent e-mails (Mailpit): http://meu-app.localhost:8024')
        ->toContain('COMPOSE_PROFILES=db-port')
        ->toContain("    cd {$this->project}\n    docker compose up -d\n")
        ->not->toContain('composer dev');

    expect(array_filter($this->runner->calls, fn (array $call): bool => $call[0] === 'php'))->toBe([]);
});

it('no container do instalador: fala "desta pasta" e dos comandos do Docker (sem cd)', function (): void {
    $this->runner->withCompose = true;

    expect(($this->create)(['TWS_KIT_IN_DOCKER' => '1']))->toBe(0);

    expect(($this->output)())->toContain('creating the project in this folder')
        ->toContain('Project ready in this folder')
        ->toContain("Next steps:\n    docker compose up -d\n")
        ->not->toContain('    cd ');
});

it('no container, falha depois de montar o projeto: recomeçar do ZIP (sem comandos de composer na máquina)', function (): void {
    $this->runner->failing['update'] = 1;

    expect(($this->create)(['TWS_KIT_IN_DOCKER' => '1']))->toBe(1);

    expect(($this->output)())->toContain('The project in this folder is incomplete')
        ->toContain('Download the twstec-kit ZIP again, into a new folder')
        ->not->toContain('    composer update');
});

it('no container, desistir no menu: nada instalado, e o comando para rodar de novo', function (): void {
    expect(($this->create)(['TWS_KIT_IN_DOCKER' => '1'], fn (): ?Choice => null))->toBe(1)
        ->and(($this->output)())->toContain('Run `docker compose run --rm instalar` again whenever you want.');
});

// --- extensões do PHP (ver também PlatformTest) ------------------------------

it('Windows: TODAS as chamadas ao Composer ignoram só ext-pcntl e ext-posix — e o resumo avisa do Horizon', function (): void {
    expect(($this->create)([], null, 'en', 'Windows'))->toBe(0);

    foreach (array_filter($this->runner->calls, fn (array $call): bool => $call[0] === 'composer') as $call) {
        expect($call[2]['COMPOSER_IGNORE_PLATFORM_REQ'] ?? null)->toBe('ext-pcntl,ext-posix', implode(' ', $call[1]));
    }

    expect(($this->output)())->toContain('Windows: Horizon (the queue dashboard) needs the pcntl and posix extensions')
        ->toContain('php artisan queue:work')
        ->toContain('docker compose run --rm instalar');
});

it('fora do Windows, nada é ignorado sem pedido; o que foi pedido (variável ou opção do create-project) chega a todas', function (): void {
    ($this->create)();

    foreach (array_filter($this->runner->calls, fn (array $call): bool => $call[0] === 'composer') as $call) {
        expect($call[2])->not->toHaveKey('COMPOSER_IGNORE_PLATFORM_REQ')->not->toHaveKey('COMPOSER_IGNORE_PLATFORM_REQS');
    }

    expect(($this->output)())->not->toContain('Horizon');

    $this->runner->calls = [];
    ($this->writeKit)();
    ($this->create)(['COMPOSER_IGNORE_PLATFORM_REQ' => 'ext-intl'], null, 'en', 'Linux', ['composer', 'create-project', 'twstec/kit', 'x', '--ignore-platform-req=ext-zip']);

    foreach (array_filter($this->runner->calls, fn (array $call): bool => $call[0] === 'composer') as $call) {
        expect($call[2]['COMPOSER_IGNORE_PLATFORM_REQ'] ?? null)->toBe('ext-intl,ext-zip');
    }
});

it('extensão que falta no composer update: a lista, as duas saídas (instalar ou Docker) e como terminar', function (): void {
    $this->runner->failing['update'] = 2;
    $this->runner->failingOutput['update'] = MISSING_EXTENSIONS_OUTPUT;

    expect(($this->create)())->toBe(1)
        ->and($this->runner->captured)->toBe(['update']);

    expect(($this->output)())->toContain('PHP extensions missing on this machine: bcmath, gd. There are two ways out:')
        ->toContain('extension=bcmath, extension=gd')
        ->toContain('sudo apt install php8.4-bcmath php8.4-gd')
        ->toContain('Or use the Docker-only way')
        ->toContain('docker compose run --rm instalar')
        ->toContain("With the extensions installed, to finish without starting over:\n    cd {$this->project}\n    composer update\n    composer run-script post-create-project-cmd\n");
});

it('no Windows, a mesma falha: os comandos de terminar começam pelo que foi ignorado', function (): void {
    $this->runner->failing['update'] = 2;
    $this->runner->failingOutput['update'] = MISSING_EXTENSIONS_OUTPUT;

    ($this->create)([], null, 'en', 'Windows');

    expect(($this->output)())->toContain("    cd {$this->project}\n    \$env:COMPOSER_IGNORE_PLATFORM_REQ = \"ext-pcntl,ext-posix\"\n    composer update\n");
});

it('falha do composer update sem ser extensão: a mensagem de sempre, sem a lista', function (): void {
    $this->runner->failing['update'] = 1;
    $this->runner->failingOutput['update'] = 'Could not find package twstec/kit-foundation';

    ($this->create)();

    expect(($this->output)())->not->toContain('PHP extensions missing')
        ->toContain('To finish without starting over, after fixing the cause:');
});
