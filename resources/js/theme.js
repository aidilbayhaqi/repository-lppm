const root = document.documentElement;
const system = window.matchMedia('(prefers-color-scheme: dark)');
const pickers = document.querySelectorAll('[data-theme-picker]');
const apply = (value) => {
    const preference = ['light', 'dark', 'system'].includes(value) ? value : 'system';
    root.dataset.themePreference = preference;
    root.dataset.theme = preference === 'system' ? (system.matches ? 'dark' : 'light') : preference;
    pickers.forEach((picker) => { picker.value = preference; });
};
apply(root.dataset.themePreference || 'system');
pickers.forEach((picker) => picker.addEventListener('change', () => {
    apply(picker.value);
    try { localStorage.setItem('repository-theme', picker.value); } catch {}
}));
system.addEventListener('change', () => apply(root.dataset.themePreference));
window.addEventListener('storage', (event) => {
    if (event.key === 'repository-theme') apply(event.newValue || 'system');
});
