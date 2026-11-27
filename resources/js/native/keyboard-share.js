/*
 * Keyboard: --keyboard-inset holds the height the on-screen keyboard covers
 * (visualViewport), so bottom sheets and dialogs can sit above it instead of
 * behind it; --visible-height is what is left above the keyboard, so a sheet
 * lifted above it is also shortened to fit (html.keyboard-open meanwhile).
 *
 * Share: window.shareOrCopy({ title, text, url }) opens the iOS share sheet,
 * or copies to the clipboard where there is none; a short toast confirms.
 */
const root = document.documentElement;

const updateKeyboardInset = () => {
    const viewport = window.visualViewport;

    if (! viewport) {
        return;
    }

    const covered = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
    root.style.setProperty('--keyboard-inset', `${Math.round(covered)}px`);
    root.style.setProperty('--visible-height', `${Math.round(viewport.height)}px`);
    root.classList.toggle('keyboard-open', covered > 80);
};

window.visualViewport?.addEventListener('resize', updateKeyboardInset);
window.visualViewport?.addEventListener('scroll', updateKeyboardInset);

// A field focused inside a sheet scrolls into view once the keyboard is up.
document.addEventListener('focusin', (event) => {
    if (event.target.matches?.('input, textarea, select') && event.target.closest('[data-sheet-panel], dialog')) {
        setTimeout(() => event.target.scrollIntoView({ block: 'center', behavior: 'smooth' }), 300);
    }
});

const toast = (text) => {
    const el = document.createElement('div');
    el.className = 'native-toast';
    el.setAttribute('role', 'status');
    el.textContent = text;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add('is-hiding'), 1600);
    setTimeout(() => el.remove(), 2000);
};

window.nativeToast = toast;

window.shareOrCopy = async ({ title, text, url }) => {
    if (navigator.share) {
        try {
            await navigator.share({ title, text, url });
        } catch {
            // the user closed the share sheet
        }

        return;
    }

    try {
        await navigator.clipboard.writeText([text ?? title, url].filter(Boolean).join('\n'));
        toast(document.documentElement.lang === 'pl' ? 'Skopiowano' : 'Copied');
    } catch {
        // clipboard unavailable — nothing else to do
    }
};
