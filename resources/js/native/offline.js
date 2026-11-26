/*
 * Shopping with bad reception:
 *  - the service worker keeps the last shopping list / custom list pages, so
 *    they open without a connection (sw.js);
 *  - ticking items off while offline is queued (localStorage) and sent once
 *    the connection is back — window.offlineCall($wire, name, method, args);
 *  - a small pill says the app is offline;
 *  - signing out clears the stored pages.
 */
const QUEUE_KEY = 'offline-queue';

const readQueue = () => {
    try {
        return JSON.parse(localStorage.getItem(QUEUE_KEY)) || [];
    } catch {
        return [];
    }
};

const writeQueue = (queue) => {
    try {
        queue.length ? localStorage.setItem(QUEUE_KEY, JSON.stringify(queue)) : localStorage.removeItem(QUEUE_KEY);
    } catch {
        // storage unavailable — the change is lost when offline
    }
};

const isPl = () => document.documentElement.lang === 'pl';

let unreachable = false;

const queue = (call) => {
    writeQueue([...readQueue(), call]);
    unreachable = true;
    updatePill();
    window.nativeToast?.(isPl() ? 'Brak połączenia — wyślemy później' : 'No connection — will sync later');
};

/**
 * Calls $wire[method](...args); when it can't reach the server (offline, or
 * "online" with no real reception — common in shops) the call is queued and
 * sent once the connection is back.
 */
window.offlineCall = (wire, component, method, args = []) => {
    const call = { component, method, args };

    if (! navigator.onLine) {
        queue(call);

        return Promise.resolve();
    }

    return Promise.resolve(wire[method](...args))
        .then((result) => {
            unreachable = false;
            updatePill();

            return result;
        })
        .catch(() => queue(call));
};

const replay = async () => {
    if (! navigator.onLine || ! window.Livewire) {
        return;
    }

    const left = [];

    for (const call of readQueue()) {
        const component = window.Livewire.all().find((c) => c.name === call.component);

        if (! component) {
            left.push(call); // its screen isn't open — try again later

            continue;
        }

        try {
            await component.$wire[call.method](...call.args);
        } catch {
            left.push(call);
        }
    }

    writeQueue(left);
    unreachable = left.length > 0;
    updatePill();
};

let pill = null;

const updatePill = () => {
    if (! pill || ! document.body.contains(pill)) {
        pill = document.createElement('div');
        pill.className = 'offline-pill';
        pill.setAttribute('role', 'status');
        document.body.appendChild(pill);
    }

    pill.textContent = isPl() ? 'Offline' : 'Offline';
    pill.classList.toggle('is-visible', ! navigator.onLine || unreachable);
};

window.addEventListener('online', () => {
    updatePill();
    replay();
});

// "Online" without reception never fires an event: retry every half minute while something waits.
setInterval(() => readQueue().length && replay(), 30000);
document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && replay());
window.addEventListener('offline', updatePill);
document.addEventListener('livewire:navigated', () => {
    updatePill();
    replay();
});

document.addEventListener('submit', (event) => {
    if (event.target.action?.endsWith('/logout')) {
        navigator.serviceWorker?.controller?.postMessage({ type: 'clear-pages' });
        writeQueue([]);
    }
}, true);
