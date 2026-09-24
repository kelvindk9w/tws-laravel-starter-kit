<?php

declare(strict_types=1);

use App\Core\Audit\AuditTrail;
use App\Core\Audit\Models\AuditEvent;
use App\Core\Auth\Models\User;
use App\Demo\Catalog\Models\Product;
use App\Filament\AuditFixture\NewAdminScreen;
use App\Filament\Pages\Profile;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Support\AdminAudit;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Resources\Resource;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

// =============================================================================
// ARQUITETURA da trilha de auditoria do /admin — decisão do dono: toda ação
// de admin que altera dado fica registrada no BANCO.
//
// A captura é central (AdminAudit pendura um escopo em toda chamada Livewire
// do painel; AuditTrail grava cada created/updated/deleted de model). Este
// arquivo reprova o build nas portas que a captura sozinha não fecha:
//
// 1. componente do painel fora da cobertura do gancho;
// 2. escrita que NÃO dispara evento de model (query em massa, SQL cru,
//    *Quietly, withoutEvents) — ela mudaria dado sem deixar linha;
// 3. RECUSA sem registro: notificação de erro montada à mão em vez de
//    AdminAudit::denied() (que registra E avisa numa chamada só);
// 4. resource com escrita cujo model a captura ignora;
// 5. painel sem transação (a falha fechada depende dela).
//
// E prova o contrato positivo: uma tela NOVA, sem uma linha de auditoria,
// já grava sozinha.
// =============================================================================

/**
 * Arquivos PHP de app/Filament, com o caminho relativo.
 *
 * @return array<string, string> caminho relativo => conteúdo
 */
function adminFilamentSources(): array
{
    $sources = [];

    foreach ((new Finder)->files()->in(app_path('Filament'))->name('*.php') as $file) {
        $sources[str_replace(base_path().'/', '', $file->getRealPath())] = $file->getContents();
    }

    ksort($sources);

    return $sources;
}

/**
 * Classes Livewire declaradas em app/Filament.
 *
 * @return list<class-string<Component>>
 */
function adminLivewireComponents(): array
{
    $classes = [];

    foreach (array_keys(adminFilamentSources()) as $path) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($path, 'app/'));

        if (class_exists($class) && is_subclass_of($class, Component::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('todo componente Livewire do painel está coberto pelo escopo de auditoria (exceto as telas de autenticação)', function () {
    $componentes = adminLivewireComponents();

    expect($componentes)->not->toBeEmpty();

    $descobertos = [];

    foreach ($componentes as $classe) {
        if (is_a($classe, SimplePage::class, true)) {
            expect(AdminAudit::covers($classe))->toBeFalse("{$classe} é tela de autenticação e não deveria abrir escopo");

            continue;
        }

        $descobertos[] = $classe;

        expect(AdminAudit::covers($classe))->toBeTrue("{$classe} não passa pela trilha de auditoria");
    }

    expect($descobertos)->toContain(
        ListUsers::class,
        EditUser::class,
        Settings::class,
        Profile::class,
    );
});

it('nenhuma escrita do painel passa por fora dos eventos de model (ela sumiria da trilha)', function () {
    $proibidos = [
        // SQL/Query Builder direto — só DB::transaction() é permitido.
        '/\bDB::(?!transaction\()/' => 'DB:: (use o model; só DB::transaction é permitido)',
        '/Quietly\s*\(/' => '*Quietly() não dispara evento de model',
        '/withoutEvents\s*\(|withoutEventDispatcher/' => 'withoutEvents() desliga a captura',
        '/::truncate\s*\(|->truncate\s*\(/' => 'truncate()',
        '/->upsert\s*\(|::upsert\s*\(/' => 'upsert()',
        '/(?:->|::)insert(?:OrIgnore|GetId|Using)?\s*\(/' => 'insert() em massa',
        '/->(?:increment|decrement)(?:Each)?\s*\(/' => 'increment()/decrement() em massa',
    ];

    $violacoes = [];

    foreach (adminFilamentSources() as $path => $source) {
        foreach ($proibidos as $regex => $motivo) {
            if (preg_match($regex, $source) === 1) {
                $violacoes[] = "{$path}: {$motivo}";
            }
        }

        // update()/delete() encadeado numa CONSULTA (sentença única, sem
        // evento). No registro carregado ($record->update(), $record->delete())
        // o evento dispara e a captura pega — isso continua permitido.
        foreach (explode(';', $source) as $sentenca) {
            if (preg_match('/(?:::query\(\)|::where\w*\(|->where\w*\()/', $sentenca) === 1
                && preg_match('/->(?:update|delete|forceDelete)\s*\(/', $sentenca) === 1) {
                $violacoes[] = "{$path}: update/delete em massa numa consulta — ".trim(Str::limit($sentenca, 120));
            }
        }
    }

    expect($violacoes)->toBe([]);
});

it('toda recusa do painel passa por AdminAudit::denied() — não existe recusar sem registrar', function () {
    // As telas de autenticação (app/Filament/Auth e Pages/Auth) recusam
    // credencial e CÓDIGO DE LOGIN, não ação de admin: ninguém está logado
    // ainda (e a trilha de requisições já registra essas tentativas). É a
    // única exceção.
    $permitidos = ['app/Filament/Support/AdminAudit.php'];

    $violacoes = [];

    foreach (adminFilamentSources() as $path => $source) {
        if (in_array($path, $permitidos, true)
            || str_starts_with($path, 'app/Filament/Auth/')
            || str_starts_with($path, 'app/Filament/Pages/Auth/')) {
            continue;
        }

        if (preg_match('/Notification::make\(\)[^;]*->danger\(\)/s', $source) === 1) {
            $violacoes[] = $path;
        }
    }

    expect($violacoes)->toBe([], 'Recusa montada à mão (Notification ...->danger()) não fica na trilha. Use AdminAudit::denied().');
});

it('resource com escrita não pode ter o model ignorado pela captura', function () {
    $trail = app(AuditTrail::class);

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        /** @var class-string<resource> $resource */
        $model = new ($resource::getModel());

        if (! $trail->ignores($model)) {
            continue;
        }

        expect($resource::canCreate())->toBeFalse("{$resource} cria registros de um model ignorado pela trilha")
            ->and($resource::hasPage('create') || $resource::hasPage('edit'))->toBeFalse("{$resource} edita um model ignorado pela trilha");
    }
});

it('o painel roda Actions e Criar/Salvar em transação — a base da falha fechada', function () {
    expect(Filament::getPanel('admin')->hasDatabaseTransactions())->toBeTrue();
});

it('uma tela NOVA do painel, sem nenhuma linha de auditoria, já grava na trilha', function () {
    require_once base_path('tests/Fixtures/Audit/NewAdminScreen.php');

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $produto = Product::factory()->create(['title' => 'Cadeira']);

    expect(AdminAudit::covers(NewAdminScreen::class))->toBeTrue();

    Livewire::test(NewAdminScreen::class)->call('archiveOld', $produto->uuid);

    $evento = AuditEvent::query()->where('subject_uuid', $produto->uuid)->sole();

    expect($evento->action)->toBe('product.archive_old')
        ->and($evento->actor_uuid)->toBe($admin->uuid)
        ->and($evento->changes['title'])->toBe(['before' => 'Cadeira', 'after' => 'Cadeira (arquivado)']);
})->group('demo');
