/*
 * Long lists split by letters (recipes, ingredients).
 *
 * window.jumpToLetter(id, boxId): on the desktop the list scrolls inside its
 * own box (boxId), below a sticky table header if it has one; on the phone
 * the page scrolls, leaving room for the compact title bar.
 *
 * x-data="fitHeight" on that box: on the desktop its max-height ends the box
 * just above the bottom of the window, so the list scrolls on its own while
 * the recipe card / edit panel next to it stays put.
 */
window.jumpToLetter = (id, boxId = null) => {
    const el = document.getElementById(id);
    const box = boxId && window.isDesktop() ? document.getElementById(boxId) : null;

    if (! el) {
        return;
    }

    if (box) {
        const sticky = box.querySelector('thead')?.offsetHeight ?? 0;
        box.scrollTo({ top: box.scrollTop + el.getBoundingClientRect().top - box.getBoundingClientRect().top - sticky });

        return;
    }

    el.scrollIntoView({ block: 'start', behavior: 'smooth' });
};

const BOTTOM_GAP = 32;

const registerFitHeight = (Alpine) => Alpine.data('fitHeight', () => ({
    init() {
        this.fit = () => {
            if (! window.isDesktop()) {
                this.$el.style.maxHeight = '';

                return;
            }

            const top = this.$el.getBoundingClientRect().top + window.scrollY;
            this.$el.style.maxHeight = `${Math.max(320, window.innerHeight - top - BOTTOM_GAP)}px`;
        };

        this.fit();
        window.addEventListener('resize', this.fit);
        window.addEventListener('fit-heights', this.fit);
    },

    destroy() {
        window.removeEventListener('resize', this.fit);
        window.removeEventListener('fit-heights', this.fit);
    },
}));

if (window.Alpine) {
    registerFitHeight(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerFitHeight(window.Alpine));
}

// Filters above the list can wrap onto more lines after an update: re-fit.
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => succeed(() => requestAnimationFrame(() => window.dispatchEvent(new Event('fit-heights')))));
});
