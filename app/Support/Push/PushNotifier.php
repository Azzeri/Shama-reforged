<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\ShoppingList\ShoppingListCount;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Sends Web Push notifications to the other members of the household when
 * someone changes something they share (the shopping list, custom lists,
 * household supplies). Sent after the response, so nobody waits for it;
 * does nothing without VAPID keys. Expired subscriptions are removed.
 */
class PushNotifier
{
    public static function enabled(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /** Notify everyone except $actor, after the current response. */
    public static function toOthers(?User $actor, string $title, string $body, string $url): void
    {
        if (! self::enabled() || ! $actor) {
            return;
        }

        dispatch(function () use ($actor, $title, $body, $url) {
            app(self::class)->send(
                PushSubscription::query()
                    ->where('user_id', '!=', $actor->id)
                    ->whereHas('user', fn ($query) => $query->where('push_enabled', true))
                    ->get(),
                ['title' => $title, 'body' => $body, 'url' => $url, 'badge' => ShoppingListCount::get()],
            );
        })->afterResponse();
    }

    /** @param  iterable<PushSubscription>  $subscriptions */
    public function send(iterable $subscriptions, array $payload): void
    {
        try {
            $push = new WebPush(['VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ]], ['TTL' => 3600, 'urgency' => 'normal']);

            foreach ($subscriptions as $subscription) {
                $push->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                ]), json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            foreach ($push->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
