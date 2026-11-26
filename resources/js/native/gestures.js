/*
 * Touch gestures for lists:
 *  - longPress(selector, dataKey, open): holding a row (link) opens its
 *    context menu (haptic tick); the tap that follows the long press is
 *    swallowed so the link doesn't open as well.
 *  - swipeToDelete(onDelete): a row slides with the finger to the left; let
 *    go past a third of its width and it slides out and is deleted, like
 *    the full swipe in iOS lists.
 */
const HOLD_MS = 480;

const registerGestures = (Alpine) => {
    Alpine.data('longPress', (selector, dataKey, open) => ({
        init() {
            let timer = null;
            let fired = false;
            let start = null;

            const cancel = () => clearTimeout(timer);

            this.$el.addEventListener('touchstart', (event) => {
                const row = event.target.closest?.(selector);
                fired = false;

                if (! row || event.touches.length !== 1) {
                    return;
                }

                start = { x: event.touches[0].clientX, y: event.touches[0].clientY };
                timer = setTimeout(() => {
                    fired = true;
                    window.haptic?.();
                    open(Number(row.dataset[dataKey]));
                }, HOLD_MS);
            }, { passive: true });

            this.$el.addEventListener('touchmove', (event) => {
                if (start && Math.hypot(event.touches[0].clientX - start.x, event.touches[0].clientY - start.y) > 8) {
                    cancel();
                }
            }, { passive: true });

            this.$el.addEventListener('touchend', cancel);
            this.$el.addEventListener('touchcancel', cancel);
            this.$el.addEventListener('contextmenu', (event) => {
                if (event.target.closest?.(selector)) {
                    event.preventDefault();
                }
            });

            // The tap that ends a long press must not open the link — nor land
            // on the menu that just opened under the finger (its backdrop).
            const swallow = (event) => {
                if (! fired) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (event.type === 'click') {
                    fired = false;
                }
            };

            document.addEventListener('mousedown', swallow, true);
            document.addEventListener('mouseup', swallow, true);
            document.addEventListener('click', swallow, true);
            this.$el.addEventListener('touchend', () => {
                if (fired) {
                    setTimeout(() => { fired = false; }, 700);
                }
            });
            this.cleanup = () => {
                document.removeEventListener('mousedown', swallow, true);
                document.removeEventListener('mouseup', swallow, true);
                document.removeEventListener('click', swallow, true);
            };
        },
        destroy() {
            this.cleanup?.();
        },
    }));

    Alpine.data('swipeToDelete', (onDelete) => ({
        dx: 0,
        dragging: false,
        removing: false,
        start: null,
        horizontal: null,
        touchstart(event) {
            this.start = { x: event.touches[0].clientX, y: event.touches[0].clientY };
            this.horizontal = null;
        },
        touchmove(event) {
            if (! this.start) {
                return;
            }

            const dx = event.touches[0].clientX - this.start.x;
            const dy = event.touches[0].clientY - this.start.y;
            this.horizontal ??= Math.abs(dx) > 6 ? Math.abs(dx) > Math.abs(dy) : null;

            if (! this.horizontal) {
                return;
            }

            this.dragging = true;
            this.dx = Math.min(0, dx);
        },
        touchend() {
            const width = this.$el.offsetWidth || 1;
            this.dragging = false;
            this.start = null;

            if (this.dx < -width / 3) {
                window.haptic?.();
                this.dx = -width;
                this.removing = true;
                setTimeout(() => onDelete(), 200);

                return;
            }

            this.dx = 0;
        },
    }));
};

if (window.Alpine) {
    registerGestures(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerGestures(window.Alpine));
}
