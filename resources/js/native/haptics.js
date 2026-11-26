/*
 * Haptic tick on iPhone. Safari has no Vibration API, but since iOS 17.4 a
 * checkbox with the `switch` attribute gives a light haptic when it toggles —
 * also when toggled through its <label> from script. One hidden switch is
 * reused for every tick. Elsewhere navigator.vibrate is used when available.
 */
let label = null;

const ensureSwitch = () => {
    if (label && document.body.contains(label)) {
        return label;
    }

    label = document.createElement('label');
    label.setAttribute('aria-hidden', 'true');
    label.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.setAttribute('switch', '');
    input.tabIndex = -1;
    label.appendChild(input);
    document.body.appendChild(label);

    return label;
};

window.haptic = () => {
    try {
        if (navigator.vibrate && ! /iPhone|iPad/.test(navigator.userAgent)) {
            navigator.vibrate(10);

            return;
        }

        ensureSwitch().click();
    } catch {
        // no haptics here — nothing to do
    }
};
