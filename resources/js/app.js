

import './theme';
import './register';
import { enhanceLookups } from './lookups';
import './dialogs';
import './feedback';
import './publishing';
import { enhanceSourceChoices, enhanceDocuments } from './source-choice';
import './work-wizard';
import './ui-polish';

enhanceLookups(document);
enhanceSourceChoices(document);
enhanceDocuments(document);

document.documentElement.classList.add('js-enabled');

// Keep the mobile navigation modal; desktop navigation remains a regular aside.
const sidebar = document.querySelector('[data-sidebar]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
if (sidebar && sidebarToggle) {
    const main = document.querySelector('[data-workspace-main]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const close = sidebar.querySelector('[data-sidebar-close]');
    const mobile = window.matchMedia('(max-width: 900px)');
    let open = false;

    const setSidebarOpen = (next, restoreFocus = false) => {
        open = next && mobile.matches;
        sidebar.classList.toggle('is-open', open);
        sidebarToggle.setAttribute('aria-expanded', String(open));
        sidebarToggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        backdrop.hidden = !open;
        main.inert = open;
        sidebar.inert = mobile.matches && !open;
        sidebar.hidden = mobile.matches && !open;
        document.body.classList.toggle('navigation-open', open);
        if (open) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            close.focus();
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
            if (restoreFocus) sidebarToggle.focus();
        }
    };

    sidebarToggle.addEventListener('click', () => setSidebarOpen(!open));
    close.addEventListener('click', () => setSidebarOpen(false, true));
    backdrop.addEventListener('click', () => setSidebarOpen(false, true));
    sidebar.addEventListener('click', (event) => {
        if (open && event.target.closest('a')) setSidebarOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (!open) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setSidebarOpen(false, true);
        }
        if (event.key === 'Tab') {
            const focusable = [...sidebar.querySelectorAll('a[href], button, summary, [tabindex="0"]')]
                .filter((element) => element.getClientRects().length && !element.disabled);
            const first = focusable[0];
            const last = focusable.at(-1);
            if (!sidebar.contains(document.activeElement)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    mobile.addEventListener('change', () => {
        const focusInSidebar = sidebar.contains(document.activeElement);
        setSidebarOpen(false, mobile.matches && focusInSidebar);
    });
    setSidebarOpen(false);
}

const publicToggle = document.querySelector('[data-public-toggle]');
const publicMenu = document.querySelector('[data-public-menu]');
if (publicToggle && publicMenu) {
    const header = publicToggle.closest('header');
    const mobile = window.matchMedia('(max-width: 700px)');
    const setMenuOpen = (open, restoreFocus = false) => {
        publicMenu.classList.toggle('is-open', open);
        publicToggle.setAttribute('aria-expanded', String(open));
        publicToggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        if (restoreFocus) publicToggle.focus();
    };
    publicToggle.addEventListener('click', () => {
        setMenuOpen(publicToggle.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('click', (event) => {
        if (!header.contains(event.target)) setMenuOpen(false);
    });
    document.addEventListener('focusin', (event) => {
        if (!header.contains(event.target)) setMenuOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && publicToggle.getAttribute('aria-expanded') === 'true') {
            setMenuOpen(false, true);
        }
    });
    mobile.addEventListener('change', () => setMenuOpen(false));
}

// Native details stays usable without JavaScript; show results first on phones.
const filterPanel = document.querySelector('[data-filter-panel]');
if (filterPanel) {
    const mobile = window.matchMedia('(max-width: 700px)');
    const syncFilters = () => {
        if (!filterPanel.contains(document.activeElement)) filterPanel.open = !mobile.matches;
    };
    mobile.addEventListener('change', syncFilters);
    syncFilters();
}


// Repeater inputs retain DOM order, which defines author order on the server.
document.addEventListener('click', (event) => {
    const add = event.target.closest('[data-add]');
    if (add) {
        const box = add.closest('[data-repeater]');
        const rows = box.querySelector('[data-rows]');
        if (rows.children.length >= Number(box.dataset.max)) return;
        const index = Number(box.dataset.next);
        box.dataset.next = String(index + 1);
        const template = box.querySelector('[data-template]');
        const fragment = document.createElement('template');
        fragment.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index));
        rows.append(fragment.content.cloneNode(true));
        enhanceLookups(rows.lastElementChild);
        enhanceSourceChoices(rows.lastElementChild);
        enhanceDocuments(rows.lastElementChild);
        rows.lastElementChild.querySelector('input:not(:disabled),select:not(:disabled)')?.focus();
    }
    const remove = event.target.closest('[data-remove]');
    if (remove) remove.closest('[data-row]').remove();
    const move = event.target.closest('[data-move]');
    if (move) {
        const row = move.closest('[data-row]');
        if (row.previousElementSibling) row.parentElement.insertBefore(row, row.previousElementSibling);
    }
});
