// Progressive enhancement only: navigation remains functional without JavaScript.
const navStatus = document.createElement('div');
navStatus.className = 'navigation-status';
navStatus.hidden = true;
navStatus.setAttribute('role', 'status');
navStatus.setAttribute('aria-live', 'polite');
navStatus.innerHTML = '<span class="navigation-status-spinner" aria-hidden="true"></span><span>Memuat halaman…</span>';
document.body.append(navStatus);
let currentLink = null;
let showTimer = 0;
const resetNavigation = () => {
    clearTimeout(showTimer);
    navStatus.hidden = true;
    currentLink?.removeAttribute('data-navigation-pending');
    currentLink = null;
};

document.addEventListener('click', (event) => {
    const anchor = event.target.closest?.('a[href]');
    if (!anchor || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) return;
    if (anchor.hasAttribute('download') || (anchor.target && anchor.target !== '_self')) return;
    if (anchor.hasAttribute('data-no-loading')) return;
    const target = new URL(anchor.href, location.href);
    if (target.origin !== location.origin || !['http:', 'https:'].includes(target.protocol)) return;
    // Hash-only links, downloads and new tabs must not display navigation progress.
    if (target.pathname === location.pathname && target.search === location.search && target.hash) return;
    currentLink?.removeAttribute('data-navigation-pending');
    currentLink = anchor;
    currentLink.setAttribute('data-navigation-pending', 'true');
    clearTimeout(showTimer);
    showTimer = window.setTimeout(() => { navStatus.hidden = false; }, 180);
});
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get' || event.defaultPrevented || !form.checkValidity()) return;
    clearTimeout(showTimer);
    showTimer = window.setTimeout(() => { navStatus.hidden = false; }, 180);
}, true);
window.addEventListener('pageshow', resetNavigation);
window.addEventListener('pagehide', resetNavigation);

// Deliberate transitions only. Do not hide content or animate on slow/no-JS clients.
const motionAllowed = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (motionAllowed && 'IntersectionObserver' in window) {
    const targets = document.querySelectorAll('.workspace-content > .page-heading, .workspace-content > .panel, .workspace-content > .dashboard-stats');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('ui-entered');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.01 });
    targets.forEach(el => observer.observe(el));
}
