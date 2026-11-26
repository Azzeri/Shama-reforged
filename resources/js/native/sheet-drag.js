/*
 * Bottom sheets follow the finger: dragging the handle / title area down
 * moves the sheet, letting go far or fast enough closes it (with a haptic
 * tick), otherwise it springs back. Works for our <x-ui.sheet> panels
 * ([data-sheet-panel], closed through their ✕ button) and Flux flyouts
 * (dialog[data-flux-flyout], closed like Escape). Phone layout only.
 */
const CLOSE_DISTANCE = 110;
const CLOSE_VELOCITY = 0.6; // px per ms

let drag = null;

const panelFrom = (target) => target.closest?.('[data-sheet-panel], dialog[data-flux-flyout]');

const inGrabArea = (panel, touch) => {
    const top = panel.getBoundingClientRect().top;

    // The handle + title strip, or anywhere when the content is scrolled to the top.
    const scroller = panel.querySelector('[data-sheet-scroll]') || panel;

    return touch.clientY - top < 72 || scroller.scrollTop <= 0;
};

const close = (panel) => {
    if (panel.matches('dialog')) {
        // Flux listens for this on the document (same as $dispatch('modal-close')).
        document.dispatchEvent(new CustomEvent('modal-close', { detail: { name: panel.dataset.modal } }));

        return;
    }

    panel.querySelector('[data-sheet-close]')?.click();
};

document.addEventListener('touchstart', (event) => {
    const panel = panelFrom(event.target);

    if (! panel || window.isDesktop() || event.touches.length !== 1 || ! inGrabArea(panel, event.touches[0])) {
        return;
    }

    drag = { panel, startY: event.touches[0].clientY, startT: performance.now(), dy: 0, active: false };
}, { passive: true });

document.addEventListener('touchmove', (event) => {
    if (! drag) {
        return;
    }

    const dy = event.touches[0].clientY - drag.startY;

    if (! drag.active && dy < 6) {
        if (dy < -6) {
            drag = null; // scrolling up inside the sheet
        }

        return;
    }

    drag.active = true;
    drag.dy = Math.max(0, dy);
    drag.panel.style.transition = 'none';
    drag.panel.style.transform = `translateY(${drag.dy}px)`;
}, { passive: true });

const end = () => {
    if (! drag) {
        return;
    }

    const { panel, dy, startT, active } = drag;
    drag = null;

    if (! active) {
        return;
    }

    const velocity = dy / Math.max(1, performance.now() - startT);
    panel.style.transition = 'transform 260ms cubic-bezier(0.32, 0.72, 0, 1)';

    if (dy > CLOSE_DISTANCE || velocity > CLOSE_VELOCITY) {
        panel.style.transform = 'translateY(100%)';
        window.haptic?.();
        setTimeout(() => {
            close(panel);
            panel.style.removeProperty('transform');
            panel.style.removeProperty('transition');
        }, 200);

        return;
    }

    panel.style.transform = 'translateY(0)';
    setTimeout(() => panel.style.removeProperty('transition'), 280);
};

document.addEventListener('touchend', end);
document.addEventListener('touchcancel', end);
