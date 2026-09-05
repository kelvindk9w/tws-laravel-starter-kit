<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Catalog\Models\Product;
use App\Core\Showcase\Models\FormSubmission;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\BaseListRecords;
use App\Filament\Support\BaseResource;
use App\Filament\Support\ViewMode;
use Filament\Resources\Resource;
use Livewire\Livewire;

// =============================================================================
// Alternador tabela/cards das listagens do super admin (ADR-011).
//
// O que estes testes protegem:
// - o botão existe e troca de verdade o layout da listagem (contentGrid);
// - a escolha PERSISTE, por usuário (sessão) e por RECURSO — trocar
//   submissões para cards não mexe em produtos;
// - as duas telas renderizam com conteúdo nos dois modos (um alternador que
//   quebra a listagem num dos lados é pior que nenhum alternador);
// - todo Resource do painel herda da base (arquitetura).
// =============================================================================

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('começa em tabela: sem grade de cards e sem layout de card', function () {
    FormSubmission::factory()->count(3)->create();

    $table = Livewire::test(ListFormSubmissions::class)
        ->assertOk()
        ->instance()
        ->getTable();

    expect(ViewMode::for(FormSubmissionResource::class))->toBe(ViewMode::Table)
        ->and($table->getContentGrid())->toBeNull();
});

it('o botão do cabeçalho troca para cards e a listagem renderiza em grade', function () {
    $submission = FormSubmission::factory()->create(['nickname' => 'visivel-em-cards']);

    Livewire::test(ListFormSubmissions::class)
        ->callAction('toggleViewMode')
        ->assertOk()
        ->assertCanSeeTableRecords([$submission])
        ->assertSee('visivel-em-cards');

    expect(ViewMode::for(FormSubmissionResource::class))->toBe(ViewMode::Grid);

    // Numa requisição seguinte a preferência continua valendo e a tabela
    // nasce com a grade de cards configurada.
    $grid = Livewire::test(ListFormSubmissions::class)
        ->instance()
        ->getTable()
        ->getContentGrid();

    expect($grid)->toBeArray()->toHaveKey('md');
});

it('o alternador volta para a tabela no clique seguinte', function () {
    FormSubmission::factory()->create();

    Livewire::test(ListFormSubmissions::class)->callAction('toggleViewMode');
    expect(ViewMode::for(FormSubmissionResource::class))->toBe(ViewMode::Grid);

    Livewire::test(ListFormSubmissions::class)->callAction('toggleViewMode');
    expect(ViewMode::for(FormSubmissionResource::class))->toBe(ViewMode::Table);

    expect(Livewire::test(ListFormSubmissions::class)->instance()->getTable()->getContentGrid())
        ->toBeNull();
});

it('a preferência é POR RECURSO: cards em submissões não vira cards em produtos', function () {
    Livewire::test(ListFormSubmissions::class)->callAction('toggleViewMode');

    expect(ViewMode::for(FormSubmissionResource::class))->toBe(ViewMode::Grid)
        ->and(ViewMode::for(ProductResource::class))->toBe(ViewMode::Table);

    expect(Livewire::test(ListProducts::class)->instance()->getTable()->getContentGrid())
        ->toBeNull();
});

it('a preferência é POR USUÁRIO: a sessão de outro admin nasce em tabela', function () {
    Livewire::test(ListFormSubmissions::class)->callAction('toggleViewMode');
    expect(ViewMode::for(FormSubmissionResource::class))->toBe(ViewMode::Grid);

    // Sessão nova (outro operador, outra máquina) = padrão da plataforma.
    session()->flush();

    expect(ViewMode::for(FormSubmissionResource::class))->toBe(ViewMode::Table);
});

it('produtos também alternam e mostram o conteúdo nos dois modos', function () {
    $product = Product::factory()->create([
        'title' => 'Cafeteira de Prova',
        'price' => 12345,
        'description' => 'Item de teste do alternador.',
    ]);

    Livewire::test(ListProducts::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$product])
        ->assertSee('Cafeteira de Prova');

    Livewire::test(ListProducts::class)
        ->callAction('toggleViewMode')
        ->assertOk()
        ->assertCanSeeTableRecords([$product])
        ->assertSee('Cafeteira de Prova');

    expect(ViewMode::for(ProductResource::class))->toBe(ViewMode::Grid);
});

// -----------------------------------------------------------------------------
// Arquitetura: a base é lei, não convenção
// -----------------------------------------------------------------------------

it('todo Resource do painel estende a base do kit', function () {
    $resources = collect(glob(app_path('Filament/Resources/*/*Resource.php')) ?: [])
        ->map(fn (string $path): string => 'App\\Filament\\Resources\\'
            .str_replace('/', '\\', str_replace([app_path('Filament/Resources/'), '.php'], '', $path)))
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, Resource::class));

    expect($resources)->not->toBeEmpty();

    foreach ($resources as $class) {
        expect(is_subclass_of($class, BaseResource::class))
            ->toBeTrue("{$class} precisa estender App\\Filament\\Support\\BaseResource");
    }
});

it('toda página de listagem do painel estende a base (é de lá que vem o alternador)', function () {
    $pages = collect(glob(app_path('Filament/Resources/*/Pages/List*.php')) ?: [])
        ->map(fn (string $path): string => 'App\\Filament\\Resources\\'
            .str_replace('/', '\\', str_replace([app_path('Filament/Resources/'), '.php'], '', $path)));

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $class) {
        expect(is_subclass_of($class, BaseListRecords::class))
            ->toBeTrue("{$class} precisa estender App\\Filament\\Support\\BaseListRecords");
    }
});

it('todo resource declara rótulo e grupo de navegação traduzidos (nada de chave crua na tela)', function () {
    $resources = collect(glob(app_path('Filament/Resources/*/*Resource.php')) ?: [])
        ->map(fn (string $path): string => 'App\\Filament\\Resources\\'
            .str_replace('/', '\\', str_replace([app_path('Filament/Resources/'), '.php'], '', $path)))
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, BaseResource::class));

    foreach ($resources as $class) {
        expect($class::getModelLabel())->not->toContain('admin.')
            ->and($class::getPluralModelLabel())->not->toContain('admin.')
            ->and($class::getNavigationGroup())->not->toContain('admin.');
    }
});
