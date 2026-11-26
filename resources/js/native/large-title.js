/*
 * iOS large titles: when the big screen title scrolls away, a compact bar
 * with the same title fades in at the top (blurred background, under the
 * status bar). Phone layout only. Titles opt in with [data-large-title].
 */
let bar = null;
let observer = null;

const ensureBar = () => {
    if (bar && document.body.contains(bar)) {
        return bar;
    }

    bar = document.createElement('div');
    bar.className = 'compact-title-bar';
    bar.setAttribute('aria-hidden', 'true');
    bar.innerHTML = '<span></span>';
    document.body.appendChild(bar);

    return bar;
};

const watch = () => {
    observer?.disconnect();
    const title = document.querySelector('[data-large-title]');
    const el = ensureBar();
    el.classList.remove('is-visible');

    if (! title) {
        return;
    }

    el.firstChild.textContent = title.textContent.trim();
    observer = new IntersectionObserver(([entry]) => {
        el.classList.toggle('is-visible', ! entry.isIntersecting && entry.boundingClientRect.top < 0 && ! window.isDesktop());
    }, { rootMargin: '-40px 0px 0px 0px' });
    observer.observe(title);
};

document.addEventListener('livewire:navigated', watch);
