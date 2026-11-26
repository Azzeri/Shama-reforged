/*
 * iOS-like page changes on the phone:
 *  - going deeper slides the new screen in from the right (the old one moves
 *    a bit left and dims), going back does the reverse; tab switches are
 *    instant, like a tab bar;
 *  - every tab remembers its scroll position;
 *  - tapping the active tab scrolls back to the top.
 *
 * Livewire swaps the whole <body> synchronously, so the outgoing page is a
 * snapshot (a clone of the content, ignored by Alpine) animated over the new
 * one. Off with prefers-reduced-motion and on the desktop layout.
 */
const DURATION = 320;
const EASING = 'cubic-bezier(0.32, 0.72, 0, 1)'; // UIKit-like spring curve
const SCROLL_KEY = 'tab-scroll';

let kind = null;      // 'push' | 'back' | 'tab'
let snapshot = null;
let snapshotScroll = 0;
let rootClick = false;

const animated = () => ! window.isDesktop() && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const content = () => document.querySelector('[data-flux-main]');

const readScrolls = () => {
    try {
        return JSON.parse(sessionStorage.getItem(SCROLL_KEY)) || {};
    } catch {
        return {};
    }
};

const rootPathOf = (path) => '/' + (path.split('/')[1] || '');

// Tab bar / side menu taps: remember the click, and the active tab scrolls to the top instead.
const onRootPress = (event) => {
    const link = event.target.closest?.('[data-nav-root]');

    if (! link) {
        return;
    }

    const target = new URL(link.href);

    if (target.pathname === window.location.pathname && target.search === window.location.search) {
        event.preventDefault();
        event.stopPropagation();

        if (event.type === 'click') {
            window.scrollTo({ top: 0, behavior: animated() ? 'smooth' : 'auto' });
        }

        return;
    }

    rootClick = true;
};

document.addEventListener('mousedown', onRootPress, true);
document.addEventListener('click', onRootPress, true);

document.addEventListener('livewire:navigate', (event) => {
    kind = event.detail?.history ? 'back' : (rootClick ? 'tab' : 'push');
    rootClick = false;

    // Remember where this tab was scrolled to.
    try {
        const scrolls = readScrolls();
        scrolls[rootPathOf(window.location.pathname)] = window.scrollY;
        sessionStorage.setItem(SCROLL_KEY, JSON.stringify(scrolls));
    } catch {
        // storage unavailable — tabs just start at the top
    }
});

// Right before the swap: take a snapshot of what is on screen.
document.addEventListener('livewire:navigating', () => {
    snapshot = null;

    if (! animated() || kind === 'tab' || ! content()) {
        return;
    }

    snapshot = content().cloneNode(true);
    snapshotScroll = window.scrollY;

    // A picture only: no Livewire components, no duplicate ids.
    snapshot.querySelectorAll('[wire\\:id], [wire\\:snapshot], [id]').forEach((el) => {
        el.removeAttribute('wire:id');
        el.removeAttribute('wire:snapshot');
        el.removeAttribute('id');
    });
});

document.addEventListener('livewire:navigated', () => {
    if (kind === 'tab') {
        const y = readScrolls()[rootPathOf(window.location.pathname)];

        if (y) {
            requestAnimationFrame(() => window.scrollTo(0, y));
        }
    }

    const incoming = content();

    if (! snapshot || ! incoming) {
        kind = null;

        return;
    }

    const back = kind === 'back';
    const overlay = document.createElement('div');
    overlay.setAttribute('x-ignore', '');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.inert = true;
    overlay.style.cssText = `position:fixed;inset:0;z-index:${back ? 35 : 5};overflow:hidden;pointer-events:none;background:var(--color-cream)`;
    snapshot.style.cssText += `;position:absolute;left:0;right:0;top:${-snapshotScroll}px;margin:0 auto;max-width:36rem`;
    overlay.appendChild(snapshot);
    document.body.appendChild(overlay);

    const shade = '0 0 40px rgba(31,27,22,0.18)';
    const timing = { duration: DURATION, easing: EASING, fill: 'both' };

    incoming.style.position = 'relative';
    incoming.style.zIndex = back ? '1' : '20';
    incoming.style.background = 'var(--color-cream)';

    const animations = back
        ? [
            overlay.animate([{ transform: 'translateX(0)', boxShadow: shade }, { transform: 'translateX(100%)', boxShadow: shade }], timing),
            incoming.animate([{ transform: 'translateX(-28%)', opacity: 0.7 }, { transform: 'translateX(0)', opacity: 1 }], timing),
        ]
        : [
            overlay.animate([{ transform: 'translateX(0)', opacity: 1 }, { transform: 'translateX(-28%)', opacity: 0.7 }], timing),
            incoming.animate([{ transform: 'translateX(100%)', boxShadow: shade }, { transform: 'translateX(0)', boxShadow: shade }], timing),
        ];

    Promise.all(animations.map((animation) => animation.finished)).finally(() => {
        overlay.remove();
        incoming.style.removeProperty('position');
        incoming.style.removeProperty('z-index');
        incoming.style.removeProperty('background');
        animations.forEach((animation) => animation.cancel());
    });

    snapshot = null;
    kind = null;
});
