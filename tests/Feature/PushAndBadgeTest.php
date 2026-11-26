<?php

use App\Models\PushSubscription;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Push\PushNotifier;
use Livewire\Livewire;

beforeEach(fn () => app()->setLocale('pl'));

test('a device subscribes to notifications and unsubscribes again', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $subscription = ['endpoint' => 'https://web.push.apple.com/abc123', 'keys' => ['p256dh' => 'BPk', 'auth' => 'xyz']];

    $this->postJson('/push/subscriptions', $subscription)->assertOk();
    $this->postJson('/push/subscriptions', $subscription)->assertOk(); // same device: no duplicate

    expect(PushSubscription::query()->sole()->only(['user_id', 'public_key']))->toBe(['user_id' => $user->id, 'public_key' => 'BPk']);

    $this->deleteJson('/push/subscriptions', ['endpoint' => $subscription['endpoint']])->assertOk();

    expect(PushSubscription::query()->count())->toBe(0);
});

test('only https push endpoints are accepted', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/push/subscriptions', ['endpoint' => 'http://evil.test/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])
        ->assertUnprocessable();
});

test('notifications go to the other members of the household, not to the one who made the change', function () {
    config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);
    [$me, $partner] = User::factory()->count(2)->create();
    PushSubscription::query()->create(['user_id' => $me->id, 'endpoint' => 'https://push.test/me', 'endpoint_hash' => hash('sha256', 'https://push.test/me'), 'public_key' => 'k', 'auth_token' => 'a']);
    PushSubscription::query()->create(['user_id' => $partner->id, 'endpoint' => 'https://push.test/partner', 'endpoint_hash' => hash('sha256', 'https://push.test/partner'), 'public_key' => 'k', 'auth_token' => 'a']);

    $sent = [];
    app()->instance(PushNotifier::class, new class($sent) extends PushNotifier
    {
        public function __construct(private array &$sent) {}

        public function send(iterable $subscriptions, array $payload): void
        {
            foreach ($subscriptions as $subscription) {
                $this->sent[] = [$subscription->endpoint, $payload['body']];
            }
        }
    });

    $this->actingAs($me);
    Livewire::test('pages::shopping-list.index')
        ->set('newItemName', 'Chleb')
        ->call('addItem');

    // Livewire's test request ends like a real one: the after-response job has run.

    expect($sent)->toBe([['https://push.test/partner', $me->name.': dodano „Chleb” do listy zakupów']]);
});

test('the sync endpoint reports how many things are left to buy (app icon badge)', function () {
    $this->actingAs(User::factory()->create());
    ShoppingList::main()->items()->create(['name' => 'Chleb', 'is_checked' => false]);
    ShoppingList::main()->items()->create(['name' => 'Masło', 'is_checked' => true, 'checked_at' => now()]);

    expect($this->getJson('/sync/shopping')->json('count'))->toBe(1);
});

test('the notifications switch shows in the account only when push is configured', function () {
    $this->actingAs(User::factory()->create());

    config(['services.webpush.public_key' => null]);
    Livewire::test('pages::settings.profile')->assertDontSee('Powiadomienia');

    config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);
    Livewire::test('pages::settings.profile')->assertSee('Powiadomienia');
});
