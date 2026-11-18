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

// Only Firefox has this problem — elsewhere don't pay for walking the DOM.
if (navigator.userAgent.includes('Firefox')) {
    document.addEventListener('livewire:navigate', () => removeInaccessibleNodes(document.documentElement));
}

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

/*
 * Shopping list row (resources/views/components/shopping-list/item-tile).
 * Registered once instead of inlining the logic into every row's markup.
 */
const registerShoppingTile = (Alpine) => Alpine.data('shoppingTile', ({ ids, bought, reveal }) => ({
    checked: bought,
    original: bought,
    pending: false,
    removing: false,
    timer: null,
    dx: 0,
    dragging: false,
    open: false,
    startX: 0,
    startY: 0,

    toggle() {
        if (this.pending) {
            clearTimeout(this.timer);
            this.pending = false;
            this.checked = this.original;

            return;
        }

        this.checked = ! this.original;
        this.pending = true;
        this.timer = setTimeout(() => {
            this.removing = true;
            setTimeout(() => this.$wire.toggle(ids, ! this.original), 250);
        }, 900);
    },
    startDrag(e) {
        this.startX = e.touches[0].clientX;
        this.startY = e.touches[0].clientY;
        this.dragging = false;
    },
    onDrag(e) {
        const dx = e.touches[0].clientX - this.startX;
        const dy = e.touches[0].clientY - this.startY;

        if (! this.dragging) {
            if (Math.abs(dx) < 8 || Math.abs(dy) > Math.abs(dx)) {
                return;
            }

            this.dragging = true;
        }

        e.preventDefault();
        const base = this.open ? -reveal : 0;
        this.dx = Math.max(-reveal, Math.min(0, base + dx));
    },
    endDrag() {
        if (this.dragging) {
            this.open = this.dx < -reveal / 2;
            this.dx = this.open ? -reveal : 0;
        }

        setTimeout(() => { this.dragging = false; }, 50);
    },
    close() {
        this.dx = 0;
        this.open = false;
    },
    tapRow() {
        if (this.dragging) {
            return;
        }

        if (this.open) {
            this.close();

            return;
        }

        this.$wire.openGroup(ids).then(() => this.$dispatch('modal-show', { name: 'item-modal' }));
    },
}));

if (window.Alpine) {
    registerShoppingTile(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerShoppingTile(window.Alpine));
}

/*
 * Loyalty cards (shopping → "Cards" tab). The barcode / QR libraries and the
 * camera scanner are loaded on demand, so they don't weigh down every page.
 */
const digitsOnly = (value) => String(value ?? '').replace(/\s+/g, '');

const eanChecksumOk = (digits) => {
    const body = digits.slice(0, -1).split('').reverse();
    const sum = body.reduce((acc, d, i) => acc + Number(d) * (i % 2 === 0 ? 3 : 1), 0);

    return (10 - (sum % 10)) % 10 === Number(digits.at(-1));
};

/** Pick a symbology when the card wasn't scanned (typed in by hand). */
const guessBarcodeFormat = (number) => {
    const digits = digitsOnly(number);

    if (/^\d+$/.test(digits)) {
        if (digits.length === 13 && eanChecksumOk(digits)) return 'EAN13';
        if (digits.length === 8 && eanChecksumOk(digits)) return 'EAN8';
        if (digits.length === 12 && eanChecksumOk(digits)) return 'UPC';
    }

    return 'CODE128';
};

window.renderCardCode = async (el, { number, codeType, format }) => {
    const value = digitsOnly(number);
    el.innerHTML = '';

    if (codeType === 'qr') {
        const { default: QRCode } = await import('qrcode');
        el.innerHTML = await QRCode.toString(value, { type: 'svg', margin: 0, errorCorrectionLevel: 'M' });

        return;
    }

    const { default: JsBarcode } = await import('jsbarcode');
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    el.appendChild(svg);

    const options = { displayValue: false, margin: 0, height: 110, width: 2.4, background: 'transparent' };

    try {
        JsBarcode(svg, value, { ...options, format: format || guessBarcodeFormat(value) });
    } catch {
        JsBarcode(svg, value, { ...options, format: 'CODE128' });
    }

    svg.removeAttribute('width');
    svg.removeAttribute('height');
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.style.width = '100%';
    svg.style.height = '110px';
};

/** ZXing format name → [code type, JsBarcode format]. */
const fromZxingFormat = (name) => ({
    QR_CODE: ['qr', null],
    EAN_13: ['barcode', 'EAN13'],
    EAN_8: ['barcode', 'EAN8'],
    UPC_A: ['barcode', 'UPC'],
    CODE_128: ['barcode', 'CODE128'],
    CODE_39: ['barcode', 'CODE39'],
    ITF: ['barcode', 'ITF'],
}[name] ?? ['barcode', null]);

const registerCardScanner = (Alpine) => Alpine.data('cardScanner', () => ({
    scanning: false,
    error: null,
    controls: null,
    supported: !! navigator.mediaDevices?.getUserMedia && window.isSecureContext,

    async start() {
        this.error = null;
        this.scanning = true;
        this.$refs.scanner?.showModal();
        await this.$nextTick();

        try {
            const [{ BrowserMultiFormatReader }, { BarcodeFormat }] = await Promise.all([
                import('@zxing/browser'),
                import('@zxing/library'),
            ]);
            const reader = new BrowserMultiFormatReader();

            this.controls = await reader.decodeFromConstraints(
                { video: { facingMode: 'environment' } },
                this.$refs.video,
                (result) => {
                    if (! result) {
                        return;
                    }

                    const [codeType, format] = fromZxingFormat(BarcodeFormat[result.getBarcodeFormat()]);
                    this.$wire.set('number', result.getText(), false);
                    this.$wire.set('codeType', codeType, false);
                    this.$wire.set('format', format, false);
                    this.$wire.$refresh();
                    this.stop();
                },
            );
        } catch (e) {
            this.error = e?.name === 'NotAllowedError' ? 'denied' : 'failed';
            this.stop();
        }
    },
    stop() {
        this.controls?.stop();
        this.controls = null;
        this.scanning = false;

        if (this.$refs.scanner?.open) {
            this.$refs.scanner.close();
        }
    },
    destroy() {
        this.stop();
    },
}));

if (window.Alpine) {
    registerCardScanner(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerCardScanner(window.Alpine));
}

/*
 * Page switches: dim the current page right away (after a short grace
 * period, so fast switches don't flicker) so a tap always gets visible
 * feedback, and optionally measure where the time goes.
 *
 * Measuring: open any page with ?perf=1 (turn off with ?perf=0). A small
 * badge then shows, for every page switch:
 *   net    — time until the server's response arrived (network + Cloudflare + server),
 *            with "app" = time Laravel itself spent (Server-Timing header)
 *   render — swapping the page and starting Alpine/Livewire on the phone
 */
(() => {
    let startedAt = null;
    let dimTimer = null;

    try {
        const flag = new URLSearchParams(window.location.search).get('perf');

        if (flag !== null) {
            localStorage.setItem('perf', flag === '1' ? '1' : '');
        }
    } catch {
        // storage unavailable — measuring just stays off
    }

    const measuring = () => {
        try {
            return localStorage.getItem('perf') === '1';
        } catch {
            return false;
        }
    };

    const showBadge = (text) => {
        let badge = document.getElementById('perf-badge');

        if (! badge) {
            badge = document.createElement('div');
            badge.id = 'perf-badge';
            badge.style.cssText = 'position:fixed;left:8px;top:calc(env(safe-area-inset-top) + 8px);z-index:200;'
                + 'background:rgba(43,33,24,.85);color:#fff;font:12px/1.4 ui-monospace,monospace;padding:4px 8px;'
                + 'border-radius:8px;pointer-events:none;white-space:pre';
            document.body.appendChild(badge);
        }

        badge.textContent = text;
    };

    let fetchedAt = null;

    document.addEventListener('livewire:navigate', (event) => {
        startedAt = performance.now();
        fetchedAt = null;

        if (! event.detail?.cached) {
            dimTimer = setTimeout(() => document.documentElement.classList.add('is-navigating'), 120);
        }
    });

    // Fired once the new page's HTML has arrived, right before the swap.
    document.addEventListener('livewire:navigating', () => {
        fetchedAt = performance.now();
    });

    document.addEventListener('livewire:navigated', () => {
        clearTimeout(dimTimer);
        document.documentElement.classList.remove('is-navigating');

        if (startedAt !== null && measuring()) {
            const end = performance.now();
            const net = fetchedAt ? Math.round(fetchedAt - startedAt) : null;
            const render = fetchedAt ? Math.round(end - fetchedAt) : null;

            // Server time from the Server-Timing header of the page request.
            const entry = performance.getEntriesByType('resource')
                .filter((e) => e.initiatorType === 'fetch' && new URL(e.name).pathname === window.location.pathname)
                .pop();
            const app = entry?.serverTiming?.find((t) => t.name === 'app')?.duration;
            const server = app !== undefined ? ` (app ${Math.round(app)} ms)` : '';

            showBadge(`${window.location.pathname}\nnet ${net ?? '?'} ms${server} · render ${render ?? '?'} ms · total ${Math.round(end - startedAt)} ms`);
        }

        startedAt = null;
    });
})();

/*
 * Start loading the next page as soon as a finger touches a link, instead of
 * after it's lifted. Links with `wire:navigate.hover` prefetch on mouseenter;
 * phones have no hover, so touchstart pretends to be one (and a scroll or a
 * cancelled touch pretends the pointer left). When the tap completes,
 * Livewire uses the prefetched (or still in-flight) response.
 */
(() => {
    const PREFETCH_LINK = 'a[wire\\:navigate\\.hover]';
    let touched = null;
    let startY = 0;

    const leave = () => {
        touched?.dispatchEvent(new MouseEvent('mouseleave'));
        touched = null;
    };

    document.addEventListener('touchstart', (event) => {
        const link = event.target.closest?.(PREFETCH_LINK);

        if (! link || event.touches.length !== 1) {
            return;
        }

        touched = link;
        startY = event.touches[0].clientY;
        link.dispatchEvent(new MouseEvent('mouseenter'));
    }, { passive: true });

    document.addEventListener('touchmove', (event) => {
        if (touched && Math.abs(event.touches[0].clientY - startY) > 10) {
            leave();
        }
    }, { passive: true });

    document.addEventListener('touchcancel', leave, { passive: true });
    document.addEventListener('touchend', () => { touched = null; }, { passive: true });
})();
