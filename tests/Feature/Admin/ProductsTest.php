<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Catalog\Models\Product;
use App\Core\Money\Money;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use Database\Seeders\ProductSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

// CRUD de Produtos (super admin — vitrine): listar/criar/editar/excluir,
// valor monetário em centavos (nunca float), foto (upload ou URL externa),
// paginação de 10 e paginação/filtros refletidos na query string.

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('lista produtos com foto, título e valor formatado', function () {
    $product = Product::factory()->create(['title' => 'Teclado de Teste', 'price' => 34990]);

    Livewire::test(ListProducts::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$product])
        ->assertSee('Teclado de Teste')
        ->assertSee(Money::format(34990));
});

it('cria produto convertendo o valor formatado para centavos', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'title' => 'Mouse Novo',
            'price' => '1.234,56',
            'description' => 'Descrição do mouse.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('title', 'Mouse Novo')->sole();

    expect($product->price)->toBe(123456); // inteiro em centavos, nunca float
});

it('rejeita valor monetário inválido', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(['title' => 'X', 'price' => 'abc'])
        ->call('create')
        ->assertHasFormErrors(['price']);

    expect(Product::query()->count())->toBe(0);
});

it('edita título e valor pela tela de edição', function () {
    $product = Product::factory()->create(['title' => 'Antes', 'price' => 1000]);

    Livewire::test(EditProduct::class, ['record' => $product->uuid])
        ->assertFormSet(['price' => Money::format(1000)])
        ->fillForm(['title' => 'Depois', 'price' => '25,00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->title)->toBe('Depois')
        ->and($product->fresh()->price)->toBe(2500);
});

it('faz upload da foto no disk public', function () {
    Storage::fake('public');

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'title' => 'Com Foto',
            'price' => '10,00',
            'image' => UploadedFile::fake()->image('produto.jpg', 600, 400),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('title', 'Com Foto')->sole();

    expect($product->image)->toStartWith('products/')
        ->and(Storage::disk('public')->exists($product->image))->toBeTrue()
        ->and($product->imageUrl())->toContain('/storage/products/');
});

it('resolve URL externa de imagem direto (seeder da demo)', function () {
    $product = Product::factory()->create(['image' => 'https://picsum.photos/seed/x/600/400']);

    expect($product->imageUrl())->toBe('https://picsum.photos/seed/x/600/400');
});

it('exclui produto pela ação da tabela', function () {
    $product = Product::factory()->create();

    Livewire::test(ListProducts::class)
        ->callTableAction('delete', $product);

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});

it('pagina de 10 em 10 e a página vem da query string (?page=N)', function () {
    // created_at explícito: ordenação estável (o default sort é created_at desc).
    $products = collect(range(0, 24))->map(
        fn (int $i): Product => Product::factory()->create(['created_at' => now()->subMinutes($i)]),
    );

    // Página 1 (padrão): os 10 mais recentes.
    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords($products->slice(0, 10)->all())
        ->assertCanNotSeeTableRecords($products->slice(10, 10)->all());

    // A página 2 chega PELA URL (query string nativa do Livewire) e mostra
    // os 10 seguintes — ?page=2 é compartilhável por link.
    Livewire::withQueryParams(['page' => 2])
        ->test(ListProducts::class)
        ->assertCanSeeTableRecords($products->slice(10, 10)->all())
        ->assertCanNotSeeTableRecords($products->slice(0, 10)->all());
});

it('o filtro vem da query string (?filters[price_range]=…)', function () {
    $baratos = Product::factory()->count(2)->create(['price' => 5000]);
    $medios = Product::factory()->count(2)->create(['price' => 30000]);
    $caros = Product::factory()->count(2)->create(['price' => 90000]);

    Livewire::withQueryParams(['filters' => ['price_range' => ['value' => 'ate_100']]])
        ->test(ListProducts::class)
        ->assertCanSeeTableRecords($baratos->all())
        ->assertCanNotSeeTableRecords($medios->all())
        ->assertCanNotSeeTableRecords($caros->all());

    Livewire::withQueryParams(['filters' => ['price_range' => ['value' => 'acima_500']]])
        ->test(ListProducts::class)
        ->assertCanSeeTableRecords($caros->all())
        ->assertCanNotSeeTableRecords($baratos->all());
});

it('a propriedade de filtros é sincronizada na URL (#[Url])', function () {
    $property = new ReflectionProperty(ListProducts::class, 'tableFilters');

    $attributes = array_map(
        fn (ReflectionAttribute $attribute): string => $attribute->getName(),
        $property->getAttributes(),
    );

    expect($attributes)->toContain(Url::class);
});

it('o seeder da demo cadastra 30+ produtos variados', function () {
    $this->seed(ProductSeeder::class);

    expect(Product::query()->count())->toBeGreaterThanOrEqual(30)
        ->and(Product::query()->whereNull('image')->count())->toBe(0)
        ->and(Product::query()->distinct('title')->count('title'))->toBe(Product::query()->count());
});
