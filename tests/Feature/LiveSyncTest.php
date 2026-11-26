<?php

use App\Models\CustomList;
use App\Models\PantryGroup;
use App\Models\PantryItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

afterEach(fn () => Carbon::setTestNow());

function syncVersion(string $url): string
{
    return test()->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('v');
}

test('the shopping list version changes when the other phone adds, ticks or deletes something', function () {
    Carbon::setTestNow('2026-11-18 10:00:00');
    $before = syncVersion('/sync/shopping');

    $item = ShoppingList::main()->items()->create(['name' => 'Chleb', 'is_checked' => false]);
    $added = syncVersion('/sync/shopping');

    // Ticked and unticked within the same second still counts as a change.
    $item->update(['is_checked' => true]);
    $ticked = syncVersion('/sync/shopping');

    $item->delete();
    $deleted = syncVersion('/sync/shopping');

    expect([$before, $added, $ticked, $deleted])->toHaveCount(4)->each->toBeString()
        ->and(collect([$before, $added, $ticked])->unique())->toHaveCount(3)
        ->and($deleted)->not->toBe($ticked);
});

test('the shopping list version follows household supplies running low', function () {
    $group = PantryGroup::query()->create(['name' => 'Kuchnia']);
    $foil = PantryItem::query()->create(['name' => 'Folia', 'pantry_group_id' => $group->id]);
    $before = syncVersion('/sync/shopping');

    $foil->update(['status' => PantryItem::STATUS_LOW]);

    expect(syncVersion('/sync/shopping'))->not->toBe($before);
});

test('the shopping page renders the same version the endpoint returns, so its own changes do not refresh it again', function () {
    ShoppingList::main()->items()->create(['name' => 'Chleb', 'is_checked' => false]);

    $html = Livewire::test('pages::shopping-list.index')->html();

    expect($html)->toContain('data-sync-version="'.syncVersion('/sync/shopping').'"')
        ->and($html)->not->toContain('wire:poll');
});

test('a custom list has its own version and reports when it was deleted', function () {
    $list = CustomList::query()->create(['name' => 'Apteka']);
    $other = CustomList::query()->create(['name' => 'Budowlany']);
    $before = syncVersion("/sync/custom-list/{$list->id}");

    $other->items()->create(['name' => 'Silikon']);
    expect(syncVersion("/sync/custom-list/{$list->id}"))->toBe($before);

    $list->items()->create(['name' => 'Plastry']);
    expect(syncVersion("/sync/custom-list/{$list->id}"))->not->toBe($before);

    $list->delete();
    $this->getJson("/sync/custom-list/{$list->id}")->assertStatus(410);
});

test('unknown scopes and guests get nothing', function () {
    $this->getJson('/sync/recipes')->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $this->getJson('/sync/shopping')->assertRedirect(route('login'));
});
