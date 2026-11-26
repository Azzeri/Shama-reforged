<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use App\Support\RecipeImport\RecipeImporter;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('pl');
    $this->actingAs(User::factory()->create());
    Ingredient::query()->create(['name' => 'Cebula']);
    Ingredient::query()->create(['name' => 'Sól']);
    Ingredient::query()->create(['name' => 'Bulion warzywny']);
});

function pumpkinSoupJsonLd(): string
{
    $recipe = [
        '@context' => 'https://schema.org',
        '@graph' => [
            ['@type' => 'WebPage', 'name' => 'Strona'],
            [
                '@type' => 'Recipe',
                'name' => 'Zupa krem z dyni',
                'recipeYield' => '4 porcje',
                'recipeIngredient' => ['800 g dyni hokkaido', '1 cebula', '700 ml bulionu warzywnego', '100 ml śmietanki 30%', 'szczypta soli'],
                'recipeInstructions' => [
                    ['@type' => 'HowToStep', 'text' => 'Dynię i cebulę kroimy w kostkę.'],
                    ['@type' => 'HowToStep', 'text' => 'Zalewamy bulionem i gotujemy 20 minut.'],
                    ['@type' => 'HowToStep', 'text' => 'Blendujemy, dodajemy śmietankę.'],
                ],
            ],
        ],
    ];

    return '<html><head><script type="application/ld+json">'.json_encode($recipe).'</script></head><body><h1>Zupa</h1></body></html>';
}

test('a page with schema.org Recipe JSON-LD becomes a draft matched to existing ingredients', function () {
    Http::fake(['przepisy.test/*' => Http::response(pumpkinSoupJsonLd())]);

    $draft = app(RecipeImporter::class)->import('https://przepisy.test/zupa-krem-z-dyni');
    $rows = collect($draft['ingredients'])->keyBy('name');

    expect($draft['name'])->toBe('Zupa krem z dyni')
        ->and($draft['servings'])->toBe(4)
        ->and($draft['steps'])->toHaveCount(3)
        ->and($draft['host'])->toBe('przepisy.test')
        ->and($rows['Cebula'])->toMatchArray(['amount' => '1', 'unit' => 'pcs', 'isNew' => false])
        ->and($rows['Bulion warzywny'])->toMatchArray(['amount' => '700', 'unit' => 'ml', 'isNew' => false])
        ->and($rows['Dyni hokkaido'])->toMatchArray(['amount' => '800', 'unit' => 'g', 'isNew' => true])
        ->and($rows['Sól'])->toMatchArray(['amount' => '', 'original' => 'szczypta soli', 'isNew' => false]);
});

test('microdata is read when there is no JSON-LD, and a heading + list as the last resort', function () {
    Http::fake([
        'micro.test/*' => Http::response('<div itemscope itemtype="https://schema.org/Recipe"><h1 itemprop="name">Placki</h1>
            <span itemprop="recipeYield">2</span><li itemprop="recipeIngredient">200 g mąki</li><li itemprop="recipeIngredient">1 cebula</li>
            <p itemprop="recipeInstructions">Mieszamy.</p></div>'),
        'plain.test/*' => Http::response('<html><head><meta property="og:title" content="Sałatka"></head><body>
            <h2>Składniki</h2><ul><li>1 cebula</li><li>2 łyżki oliwy</li></ul><h2>Przygotowanie</h2><ol><li>Kroimy.</li></ol></body></html>'),
    ]);

    $micro = app(RecipeImporter::class)->import('https://micro.test/placki');
    $plain = app(RecipeImporter::class)->import('https://plain.test/salatka');

    expect($micro['name'])->toBe('Placki')
        ->and($micro['ingredients'])->toHaveCount(2)
        ->and($micro['steps'])->toBe(['Mieszamy.'])
        ->and($plain['name'])->toBe('Sałatka')
        ->and(collect($plain['ingredients'])->pluck('unit')->all())->toBe(['pcs', 'tbsp'])
        ->and($plain['steps'])->toBe(['Kroimy.']);
});

test('the import sheet shows what was found and the form gets the draft without saving anything', function () {
    Http::fake(['przepisy.test/*' => Http::response(pumpkinSoupJsonLd())]);

    $list = Livewire::test('pages::recipe.index')
        ->call('openImport')
        ->set('importUrl', 'https://przepisy.test/zupa-krem-z-dyni')
        ->call('importRecipe')
        ->assertSee('Znaleźliśmy')
        ->assertSee('Zupa krem z dyni')
        ->assertSee('4 porcje · 5 składników · 3 kroki · 2 nowe składniki')
        ->assertSee('Sprawdź i popraw w formularzu');

    expect(Recipe::query()->count())->toBe(0);

    $form = Livewire::test('recipe.form', ['importToken' => $list->get('importToken')])
        ->assertSet('recipeName', 'Zupa krem z dyni')
        ->assertSet('servings', 4)
        ->assertSet('recipeUrl', 'https://przepisy.test/zupa-krem-z-dyni')
        ->assertSee('Zaimportowano z przepisy.test')
        ->assertSee('nowy składnik')
        ->assertSee('W oryginale: „szczypta soli”');

    expect(Recipe::query()->count())->toBe(0);

    $form->call('save');

    $recipe = Recipe::query()->sole();
    expect($recipe->ingredients)->toHaveCount(5)
        ->and($recipe->content)->toContain('gotujemy 20 minut')
        ->and($recipe->tags)->toBeEmpty()
        ->and($recipe->rating)->toBeNull();
});

test('a failed import offers another link or filling in by hand with the link as the source', function () {
    Http::fake(['przepisy.test/*' => Http::response('Not found', 404), 'pusta.test/*' => Http::response('<html><body>Brak</body></html>')]);

    Livewire::test('pages::recipe.index')
        ->call('openImport')
        ->set('importUrl', 'https://przepisy.test/nie-ma')
        ->call('importRecipe')
        ->assertSet('importFailed', true)
        ->assertSee('Nie udało się pobrać przepisu z tej strony')
        ->assertSee('Wypełnię ręcznie')
        ->call('anotherLink')
        ->assertSet('importUrl', '')
        ->set('importUrl', 'https://pusta.test/strona')
        ->call('importRecipe')
        ->assertSet('importFailed', true);

    Livewire::test('recipe.form', ['source' => 'https://przepisy.test/nie-ma'])
        ->assertSet('recipeUrl', 'https://przepisy.test/nie-ma')
        ->assertSet('recipeName', '');
});

test('addresses inside the home network and non-http links are never fetched', function () {
    Http::fake();

    foreach (['http://127.0.0.1/admin', 'http://192.168.1.10/', 'file:///etc/passwd', 'nie-link'] as $url) {
        Livewire::test('pages::recipe.index')->set('importUrl', $url)->call('importRecipe')->assertSet('importFailed', true);
    }

    Http::assertNothingSent();
});
