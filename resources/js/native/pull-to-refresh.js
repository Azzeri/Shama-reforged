/*
 * Pull to refresh on screens marked [data-pull-refresh] (a Livewire
 * component root): pulling down at the very top shows a spinner; letting go
 * past the threshold re-renders the component from the server.
 */
const THRESHOLD = 72;
let pull = null;
let indicator = null;

const ensureIndicator = () => {
    if (indicator && document.body.contains(indicator)) {
        return indicator;
    }

    indicator = document.createElement('div');
    indicator.className = 'pull-indicator';
    indicator.setAttribute('aria-hidden', 'true');
    indicator.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>';
    document.body.appendChild(indicator);

    return indicator;
};

const setPull = (distance, armed) => {
    const el = ensureIndicator();
    el.style.opacity = String(Math.min(distance / THRESHOLD, 1));
    el.style.transform = `translate(-50%, ${Math.min(distance, THRESHOLD * 1.3) - 40}px) rotate(${distance * 3}deg)`;
    el.classList.toggle('is-armed', armed);
};

document.addEventListener('touchstart', (event) => {
    const root = document.querySelector('[data-pull-refresh][wire\\:id]');

    if (! root || window.scrollY > 0 || event.touches.length !== 1 || event.target.closest?.('[role=dialog], dialog')) {
        return;
    }

    pull = { root, startY: event.touches[0].clientY, distance: 0, armed: false };
}, { passive: true });

document.addEventListener('touchmove', (event) => {
    if (! pull) {
        return;
    }

    const distance = (event.touches[0].clientY - pull.startY) * 0.5;

    if (distance <= 0 || window.scrollY > 0) {
        setPull(0, false);

        return;
    }

    const armed = distance >= THRESHOLD;

    if (armed && ! pull.armed) {
        window.haptic?.();
    }

    pull.distance = distance;
    pull.armed = armed;
    setPull(distance, armed);
}, { passive: true });

document.addEventListener('touchend', async () => {
    if (! pull) {
        return;
    }

    const { root, armed } = pull;
    pull = null;

    if (! armed) {
        setPull(0, false);

        return;
    }

    const el = ensureIndicator();
    el.classList.add('is-loading');
    el.style.transform = 'translate(-50%, 24px)';

    try {
        await window.Livewire.find(root.getAttribute('wire:id'))?.$refresh();
    } finally {
        el.classList.remove('is-loading');
        setPull(0, false);
    }
});
