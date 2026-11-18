if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

/*
 * Some Firefox extensions (password managers etc.) inject DOM nodes the page
 * isn't allowed to touch — any property access throws "Permission denied".
 * Livewire walks the whole DOM when navigating away, hits such a node and the
 * navigation dies. Before each navigation, find those nodes and drop them
 * (the extension re-injects them on the next page if it needs them).
 */
const isInaccessible = (node) => {
    try {
        void node.nodeType;

        return false;
    } catch {
        return true;
    }
};

const removeInaccessibleNodes = (root) => {
    const stack = [root];

    while (stack.length) {
        const parent = stack.pop();
        let child;

        try {
            child = parent.firstChild;
        } catch {
            continue;
        }

        while (child) {
            let next = null;

            try {
                next = child.nextSibling;
            } catch {
                // Can't even read the sibling pointer: stop scanning this level.
            }

            if (isInaccessible(child)) {
                try {
                    parent.removeChild(child);
                } catch {
                    // Not removable either — nothing more we can do.
                }
            } else if (child.nodeType === 1) {
                stack.push(child);
            }

            child = next;
        }
    }
};

document.addEventListener('livewire:navigate', () => removeInaccessibleNodes(document.documentElement));

/*
 * In-app navigation stack (iOS-style "Back").
 *
 * We keep our own stack of screens in sessionStorage, each entry remembering
 * how many browser-history steps lead back to the previous screen. "Back"
 * then always uses history.go(-n), so Livewire restores the previous page
 * from its snapshot (same scroll, same filters) instead of navigating
 * forward to a hard-coded URL and growing the history forever.
 *
 * Rules applied on every forward navigation:
 *  - Same screen group as the current one (e.g. switching days in the day
 *    strip, day view <-> day editor): replaces the top entry, so Back skips
 *    all of them at once.
 *  - A screen already on the stack (e.g. redirect back to the recipe after
 *    saving its edit form): collapses the stack down to it, so Back doesn't
 *    return into the form.
 *  - Bottom-nav tabs are roots: the stack starts over.
 *  - Anything else: pushed.
 *
 * Back buttons carry their parent URL as a fallback for when there is no
 * in-app history (fresh launch / reload on a sub-page).
 */
const STACK_KEY = 'nav-stack';

const currentUrl = () => window.location.pathname + window.location.search;

// Screens that count as "the same place" for Back purposes.
const screenGroup = (url) => {
    const path = new URL(url, window.location.origin).pathname;

    return path.replace(/^\/meals\/day\/[^/]+(\/edit)?$/, '/meals/day');
};

const readStack = () => {
    try {
        const stack = JSON.parse(sessionStorage.getItem(STACK_KEY));

        return Array.isArray(stack) ? stack : [];
    } catch {
        return [];
    }
};

const writeStack = (stack) => {
    try {
        sessionStorage.setItem(STACK_KEY, JSON.stringify(stack));
    } catch {
        // Private mode etc. — Back falls back to parent URLs.
    }
};

let pendingKind = null; // 'push' | 'history' | 'root'

const recordNavigation = (kind) => {
    const url = currentUrl();
    const group = screenGroup(url);
    let stack = readStack();

    if (kind === 'root' || stack.length === 0) {
        writeStack([{ url, back: 0 }]);

        return;
    }

    if (kind === 'history') {
        // Browser/system back or our history.go(-n): drop entries above us.
        const index = stack.map((entry) => entry.url).lastIndexOf(url);
        writeStack(index === -1 ? [{ url, back: 0 }] : stack.slice(0, index + 1));

        return;
    }

    const top = stack[stack.length - 1];

    if (screenGroup(top.url) === group) {
        stack[stack.length - 1] = { url, back: top.back + 1 };
        writeStack(stack);

        return;
    }

    const earlier = stack.map((entry) => screenGroup(entry.url)).lastIndexOf(group);

    if (earlier !== -1) {
        const steps = stack.slice(earlier).reduce((sum, entry) => sum + entry.back, 0) + 1;
        stack = stack.slice(0, earlier);
        stack.push({ url, back: earlier === 0 ? 0 : steps });
        writeStack(stack);

        return;
    }

    stack.push({ url, back: 1 });
    writeStack(stack);
};

document.addEventListener('livewire:navigate', (event) => {
    if (event.detail?.history) {
        pendingKind = 'history';
    } else if (pendingKind !== 'root') {
        pendingKind = 'push';
    }
});

document.addEventListener('livewire:navigated', () => {
    if (pendingKind === null) {
        // Full page load: keep the stack if it still matches, else restart.
        const stack = readStack();
        const top = stack[stack.length - 1];

        if (! top || top.url !== currentUrl()) {
            writeStack([{ url: currentUrl(), back: 0 }]);
        }

        return;
    }

    recordNavigation(pendingKind);
    pendingKind = null;
});

// Bottom-nav (and any [data-nav-root]) links start a fresh stack.
document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-nav-root]')) {
        pendingKind = 'root';
    }
}, true);

window.goBack = (fallbackHref) => {
    const stack = readStack();
    const top = stack[stack.length - 1];

    if (stack.length > 1 && top?.url === currentUrl() && top.back > 0) {
        window.history.go(-top.back);

        return;
    }

    if (! fallbackHref) {
        return;
    }

    const target = new URL(fallbackHref, window.location.origin);
    pendingKind = 'root';
    window.Livewire.navigate(target.pathname + target.search);
};

/*
 * Swipe from the left screen edge to go back — standalone PWAs on iOS have
 * no system back gesture. Only active on pages that have a Back button.
 */
(() => {
    const EDGE = 24;
    const THRESHOLD = 80;
    let startX = null;
    let startY = null;
    let indicator = null;

    const backButton = () => document.querySelector('[data-back-button]');

    // Regular Safari already has its own edge-swipe back gesture.
    const isStandalone = () => window.navigator.standalone === true
        || window.matchMedia('(display-mode: standalone)').matches;

    const ensureIndicator = () => {
        if (indicator && document.body.contains(indicator)) {
            return indicator;
        }

        indicator = document.createElement('div');
        indicator.setAttribute('aria-hidden', 'true');
        indicator.style.cssText = 'position:fixed;left:0;top:50%;z-index:100;width:40px;height:40px;margin-top:-20px;'
            + 'border-radius:9999px;background:rgba(43,33,24,0.85);color:#fff;display:flex;align-items:center;'
            + 'justify-content:center;font-size:20px;pointer-events:none;opacity:0;transform:translateX(-44px);'
            + 'transition:opacity .1s';
        indicator.textContent = '‹';
        document.body.appendChild(indicator);

        return indicator;
    };

    const reset = () => {
        startX = null;

        if (indicator) {
            indicator.style.opacity = '0';
            indicator.style.transform = 'translateX(-44px)';
        }
    };

    document.addEventListener('touchstart', (event) => {
        const touch = event.touches[0];

        if (! isStandalone() || event.touches.length !== 1 || touch.clientX > EDGE || ! backButton()) {
            return;
        }

        startX = touch.clientX;
        startY = touch.clientY;
    }, { passive: true });

    document.addEventListener('touchmove', (event) => {
        if (startX === null) {
            return;
        }

        const touch = event.touches[0];
        const dx = touch.clientX - startX;
        const dy = Math.abs(touch.clientY - startY);

        if (dy > 40 && dy > dx) {
            reset();

            return;
        }

        const progress = Math.min(Math.max(dx, 0), THRESHOLD * 1.5);
        const el = ensureIndicator();
        el.style.opacity = String(Math.min(progress / THRESHOLD, 1));
        el.style.transform = `translateX(${Math.min(progress, THRESHOLD) - 44 + 12}px)`;
    }, { passive: true });

    document.addEventListener('touchend', (event) => {
        if (startX === null) {
            return;
        }

        const dx = event.changedTouches[0].clientX - startX;
        const button = backButton();
        reset();

        if (dx >= THRESHOLD && button) {
            window.goBack(button.dataset.backHref || button.getAttribute('href'));
        }
    });

    document.addEventListener('touchcancel', reset);
})();

/*
 * Navigate to a recipe while remembering the exact page (incl. query string,
 * e.g. ?week=) to come back to.
 */
window.openRecipe = (href) => {
    const url = new URL(href, window.location.origin);
    url.searchParams.set('back', window.location.pathname + window.location.search);

    window.Livewire.navigate(url.pathname + url.search);
};
