<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use App\Livewire\ApiKeys\Index;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

// =============================================================================
// <x-checkbox>: os atributos que carregam o ESTADO (wire:model, value, data-*)
// vão para o INPUT, não para a label. Até a 1.1.1 iam para a label, e uma
// LISTA de caixas com wire:model (escopos e projetos de uma chave de API) não
// sincronizava no navegador: os projetos marcados não chegavam ao servidor e
// a chave nascia valendo para a conta toda. O Livewire::test não roda o
// JavaScript — a prova no navegador é o tests/e2e/api-key-scopes.spec.js;
// aqui, a marcação (do componente sozinho e da tela de chaves de verdade).
// =============================================================================

/**
 * Lê o HTML pelo DOM.
 */
function checkboxDom(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

    return new DOMXPath($dom);
}

/**
 * O input e a label do componente renderizado sozinho.
 *
 * @return array{input: DOMElement, label: DOMElement}
 */
function renderedCheckbox(string $blade): array
{
    $xpath = checkboxDom(Blade::render($blade, ['uuid' => 'a1b2']));

    /** @var DOMElement $input */
    $input = $xpath->query('//input')->item(0);
    /** @var DOMElement $label */
    $label = $xpath->query('//label')->item(0);

    return ['input' => $input, 'label' => $label];
}

/**
 * value => elemento, das caixas da tela ligadas ao wire:model dado.
 *
 * @return array<string, DOMElement>
 */
function checkboxesBoundTo(string $html, string $model): array
{
    $boxes = [];

    foreach (checkboxDom($html)->query('//input[@type="checkbox"][@*[name()="wire:model"]="'.$model.'"]') as $input) {
        /** @var DOMElement $input */
        $boxes[$input->getAttribute('value')] = $input;
    }

    return $boxes;
}

it('numa lista, o wire:model e o valor de cada caixa vão para o input', function () {
    ['input' => $input, 'label' => $label] = renderedCheckbox('<x-checkbox class="rounded-lg" wire:model="selectedProjectUuids" value="{{ $uuid }}" data-x="1" label="Loja A" />');

    expect($input->getAttribute('wire:model'))->toBe('selectedProjectUuids')
        ->and($input->getAttribute('value'))->toBe('a1b2')
        ->and($input->getAttribute('data-x'))->toBe('1')
        ->and($input->getAttribute('type'))->toBe('checkbox')
        ->and($input->hasAttribute('name'))->toBeFalse()
        ->and($label->hasAttribute('wire:model'))->toBeFalse()
        ->and($label->hasAttribute('value'))->toBeFalse()
        ->and($label->getAttribute('class'))->toContain('rounded-lg')
        ->and($input->getAttribute('class'))->not->toContain('rounded-lg')
        ->and($label->textContent)->toContain('Loja A');
});

it('caixa única de formulário: envia "1" com o nome dado', function () {
    ['input' => $input] = renderedCheckbox('<x-checkbox name="remember" label="Lembrar de mim" :checked="true" />');

    expect($input->getAttribute('name'))->toBe('remember')
        ->and($input->getAttribute('value'))->toBe('1')
        ->and($input->hasAttribute('checked'))->toBeTrue();
});

it('a caixa "lembrar de mim" do login continua enviando remember=1', function () {
    $xpath = checkboxDom($this->get('/login')->assertOk()->getContent());

    /** @var DOMElement $input */
    $input = $xpath->query('//input[@type="checkbox"][@name="remember"]')->item(0);

    expect($input)->not->toBeNull()
        ->and($input->getAttribute('value'))->toBe('1');
});

it('tela de chaves de API: cada projeto e cada escopo é uma caixa com o próprio valor e o wire:model no input', function () {
    $user = User::factory()->create();
    $lojaA = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja A']);
    $lojaB = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja B']);

    $html = Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('allScopes', false)
        ->html();

    $projetos = checkboxesBoundTo($html, 'selectedProjectUuids');
    $escopos = checkboxesBoundTo($html, 'selectedScopes');

    $catalogo = [];
    foreach ((array) config('api_keys.scopes_catalog') as $recurso => $acoes) {
        foreach ($acoes as $acao) {
            $catalogo[] = "{$recurso}:{$acao}";
        }
    }

    expect(array_keys($projetos))->toEqualCanonicalizing([$lojaA->uuid, $lojaB->uuid])
        ->and(array_keys($escopos))->toEqualCanonicalizing($catalogo)
        ->and($catalogo)->not->toBeEmpty()
        // Nenhuma caixa da lista com o value="1" fixo do componente antigo.
        ->and(checkboxDom($html)->query('//label[@*[name()="wire:model"]]')->length)->toBe(0);
});

it('modal dos projetos de uma chave: as caixas vêm marcadas pelo vínculo e ligadas à seleção', function () {
    $user = User::factory()->create();
    $lojaA = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja A']);
    $lojaB = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja B']);
    $key = criarChave($user, ['project_uuids' => [$lojaA->uuid]])['api_key'];

    $html = Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startEditProjects', $key->uuid)
        ->assertSet('editingProjectsSelection', [$lojaA->uuid])
        ->html();

    expect(array_keys(checkboxesBoundTo($html, 'editingProjectsSelection')))
        ->toEqualCanonicalizing([$lojaA->uuid, $lojaB->uuid]);
});

it('a tela de chaves usa a caixa em lista com valor próprio nos três lugares', function () {
    $chaves = (string) file_get_contents(resource_path('views/livewire/api-keys/index.blade.php'));

    expect(substr_count($chaves, '<x-checkbox'))->toBe(3)
        ->and(substr_count($chaves, 'wire:model="selectedScopes"'))->toBe(1)
        ->and(substr_count($chaves, 'wire:model="selectedProjectUuids"'))->toBe(1)
        ->and(substr_count($chaves, 'wire:model="editingProjectsSelection"'))->toBe(1);
});
