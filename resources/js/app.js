if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

/*
 * In-app "Back" button.
 *
 * When the page we'd go back to is exactly the one we came from, use the
 * browser history instead of navigating forward to it: Livewire restores the
 * previous page from its snapshot with the same scroll position and filters,
 * and the history stack doesn't keep growing. Otherwise (e.g. after saving a
 * form, or on a fresh app launch) navigate to the given URL as before.
 */
let previousPath = null;

document.addEventListener('livewire:navigate', (event) => {
    if (! event.detail?.history) {
        previousPath = window.location.pathname;
    }
});

window.goBack = (href) => {
    const target = new URL(href, window.location.origin);

    if (previousPath !== null && previousPath === target.pathname && window.history.length > 1) {
        previousPath = null;
        window.history.back();

        return;
    }

    window.Livewire.navigate(target.pathname + target.search);
};

/*
 * Navigate to a recipe while remembering the exact page (incl. query string,
 * e.g. ?week=) to come back to.
 */
window.openRecipe = (href) => {
    const url = new URL(href, window.location.origin);
    url.searchParams.set('back', window.location.pathname + window.location.search);

    window.Livewire.navigate(url.pathname + url.search);
};
