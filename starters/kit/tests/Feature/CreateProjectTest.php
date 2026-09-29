<?php

declare(strict_types=1);

use Twstec\Kit\Setup\Choice;
use Twstec\Kit\Setup\CreateProject;
use Twstec\Kit\Setup\Output;
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

    $this->create = function (array $env = [], ?Closure $menu = null, string $locale = 'en'): int {
        return (new CreateProject($this->project, translator($locale), $this->runner, new Output($this->stream, false), $env, $menu))->run();
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

    expect($installer[2])->toBe(['TWS_KIT_WITH' => 'accounts,admin', 'TWS_KIT_WITHOUT' => 'uploads', 'APP_LOCALE' => 'en'])
        ->and(($this->output)())->toContain('Choice (no questions): React');
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
