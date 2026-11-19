document.querySelectorAll('[data-work-wizard]').forEach(form => {
    const steps = [...form.querySelectorAll('[data-form-step]')];
    const tabs = [...form.querySelectorAll('[data-step-target]')];
    const back = form.querySelector('[data-step-back]');
    const next = form.querySelector('[data-step-next]');
    const save = form.querySelector('[data-step-save]');
    let current = 0;
    form.noValidate = true;
    const show = (index, focus = false) => {
        current = index;
        steps.forEach((step, i) => { step.hidden = i !== index; });
        tabs.forEach((tab, i) => {
            if (i === index) tab.setAttribute('aria-current', 'step');
            else tab.removeAttribute('aria-current');
            tab.classList.toggle('is-complete', i < index);
        });
        back.hidden = index === 0;
        next.hidden = index === steps.length - 1;
        save.hidden = index !== steps.length - 1;
        if (focus) {
            const title = steps[index].querySelector('h2');
            title.tabIndex = -1;
            title.focus({ preventScroll: true });
            form.scrollIntoView({ block: 'start', behavior: 'auto' });
        }
    };
    const reveal = field => {
        const step = field.closest('[data-form-step]');
        if (step) show(steps.indexOf(step));
        for (let parent = field.parentElement; parent && parent !== form; parent = parent.parentElement) {
            if (parent.tagName === 'DETAILS') parent.open = true;
        }
    };
    const validate = root => {
        const invalid = [...root.querySelectorAll('input,select,textarea')].find(field => !field.disabled && !field.checkValidity());
        if (!invalid) return true;
        reveal(invalid);
        invalid.focus();
        invalid.reportValidity();
        return false;
    };
    next.addEventListener('click', () => { if (validate(steps[current])) show(current + 1, true); });
    back.addEventListener('click', () => show(current - 1, true));
    tabs.forEach((tab, index) => tab.addEventListener('click', () => {
        if (index > current) for (let i = current; i < index; i++) if (!validate(steps[i])) return;
        show(index, true);
    }));
    form.addEventListener('wizard:reveal', event => reveal(event.detail));
    form.addEventListener('submit', event => {
        // Enter follows the same progression as Lanjut; final save validates every step.
        if (current < steps.length - 1) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (validate(steps[current])) show(current + 1, true);
        } else if (!validate(form)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    show(0);
    const error = form.querySelector('[aria-invalid="true"]');
    if (error) reveal(error);
});
