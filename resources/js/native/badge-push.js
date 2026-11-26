/*
 * App icon badge: the number of things left to buy (Badging API — iOS 16.4+
 * home-screen apps). Refreshed on every screen, when the app comes back to
 * the foreground, and by push notifications (sw.js).
 *
 * Push: Alpine component `pushToggle(publicKey)` for the Account screen —
 * asks for permission, subscribes this device and sends the subscription
 * to the server (or removes it).
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const signedIn = () => !! document.querySelector('[data-nav-root]');

const refreshBadge = async () => {
    if (! ('setAppBadge' in navigator) || ! signedIn() || ! navigator.onLine) {
        return;
    }

    try {
        const response = await fetch('/sync/shopping', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        const { count } = response.ok ? await response.json() : {};

        if (typeof count === 'number') {
            count > 0 ? navigator.setAppBadge(count) : navigator.clearAppBadge();
        }
    } catch {
        // offline / signed out — keep the last badge
    }
};

document.addEventListener('livewire:navigated', refreshBadge);
document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && refreshBadge());

const urlBase64ToUint8Array = (base64) => {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
};

const registerPushToggle = (Alpine) => Alpine.data('pushToggle', (publicKey) => ({
    supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    standalone: window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches,
    enabled: false,
    busy: false,
    denied: false,
    async init() {
        if (! this.supported) {
            return;
        }

        this.denied = Notification.permission === 'denied';
        const registration = await navigator.serviceWorker.getRegistration();
        this.enabled = !! (await registration?.pushManager.getSubscription());
    },
    async toggle() {
        this.busy = true;

        try {
            this.enabled ? await this.disable() : await this.enable();
        } finally {
            this.busy = false;
        }
    },
    async enable() {
        if (await Notification.requestPermission() !== 'granted') {
            this.denied = Notification.permission === 'denied';

            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });

        const response = await fetch('/push/subscriptions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(subscription.toJSON()),
        });

        this.enabled = response.ok;
        window.haptic?.();
    },
    async disable() {
        const registration = await navigator.serviceWorker.getRegistration();
        const subscription = await registration?.pushManager.getSubscription();

        if (subscription) {
            await fetch('/push/subscriptions', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });
            await subscription.unsubscribe();
        }

        this.enabled = false;
    },
}));

if (window.Alpine) {
    registerPushToggle(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerPushToggle(window.Alpine));
}
