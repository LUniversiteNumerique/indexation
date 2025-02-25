document.addEventListener('DOMContentLoaded', () => {
    ['.ea-new-form', '.ea-edit-form'].forEach((formSelector) => {
        const form = document.querySelector(formSelector);
        if (null === form) return; //formSelector.includes('-new-') ? 'new' : 'edit')

        handleFieldsWithErrors(form, formSelector.includes('-new-') ? 'new' : 'edit');
        changeOptions(form,'Notice_champDisc','Notice_discipline','Notice_specialite')

        if(null !== document.getElementById('Notice_disciFond'))
            changeOptions(form,'Notice_disciFond','Notice_division','Notice_codewey')
    })
});
const filedErrors = (input, inputEvent) => {
    const formTab = input.closest('div.tab-pane');
    if (formTab) {
        // Match tab link either by "data-bs-target" attribute or by href linking to the id anchor
        const navLinkTab = document.querySelector(`[data-bs-target="#${ formTab.id }"], a[href="#${ formTab.id }"]`);

        if (navLinkTab) {
            navLinkTab.classList.add('has-error');

            const badge = navLinkTab.querySelector('.badge');
            if (badge) {
                // Increment number of error
                badge.textContent = (parseInt(badge.textContent) + 1).toString();
            } else {
                // Create a new badge
                let newErrorBadge = document.createElement('span');
                newErrorBadge.classList.add('badge', 'badge-danger');
                newErrorBadge.textContent = '1';
                navLinkTab.appendChild(newErrorBadge);
            }
        }
    }

    // Visual feedback for group
    const formGroup = input.closest('div.form-group');
    formGroup.classList.add('has-error');

    input.addEventListener(inputEvent, function onFormGroupClick() {
        formGroup.classList.remove('has-error');
        formGroup.removeEventListener('click', onFormGroupClick);
    });
}
const handleFieldsWithErrors = (form, pageName) => {
    const that = this;
    document.querySelector('.ea-edit, .ea-new').querySelectorAll('[type="submit"]').forEach((button) => {
        button.addEventListener('click', function onSubmitButtonsClick(clickEvent) {
            let formHasErrors = false;

            // Remove all error counter badges
            document.querySelectorAll('.form-tabs-tablist .nav-item .badge-danger.badge').forEach( (badge) => {
                badge.parentElement.removeChild(badge);
            });

            if (null !== form.getAttribute('novalidate')) {
                return;
            }

            form.querySelectorAll('select, input, textarea').forEach((input) => {
                if (!input.disabled && !input.validity.valid) {
                    formHasErrors = true;
                    filedErrors(input, 'change')
                }
            });

            /*if (formHasErrors) {
                clickEvent.preventDefault();
                clickEvent.stopPropagation();

                // set as active the first tab with errors
                const firstTabWithErrors = document.querySelector('.form-tabs-tablist .nav-tabs .nav-item .nav-link.has-error');
                if (null !== firstTabWithErrors) {
                    that.#setTabAsActive(firstTabWithErrors.id);
                }

                document.dispatchEvent(new CustomEvent('ea.form.error', {
                    cancelable: true,
                    detail: { page: pageName, form: form }
                }));
            }*/
        });
    });

    form.addEventListener('submit', (submitEvent) => {
        const eaEvent = new CustomEvent('ea.form.submit', {
            cancelable: true,
            detail: { page: pageName, form: form }
        });
        const eaEventResult = document.dispatchEvent(eaEvent);
        if (false === eaEventResult) {
            submitEvent.preventDefault();
            submitEvent.stopPropagation();
        }
    });
};

const updateForm = async (data, form) => {
    const action = form.getAttribute('action');
    const req = await fetch(action, {
        method: form.getAttribute('method'), body: data,
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'charset': 'utf-8'}
    });

    return (new DOMParser()).parseFromString(await req.text(), 'text/html');
};

const changeOptions = (form, champ,disci,speci) => {
    const form_select_champ = document.getElementById(champ);
    const form_select_disci = document.getElementById(disci);
    const form_select_speci = document.getElementById(speci);

    form_select_champ.addEventListener('change', async ({target}) => {
        const resText = await updateForm(`${target.getAttribute('name')}=${target.value}`,form);
        form_select_disci.innerHTML = resText.getElementById(disci).innerHTML
        form_select_speci.innerHTML = resText.getElementById(speci).innerHTML
    });
    form_select_disci.addEventListener('change', async ({target}) => {
        const reqBody = `${form_select_champ.getAttribute('name')}=${form_select_champ.value}&${target.getAttribute('name')}=${target.value}`
        form_select_speci.innerHTML = (await updateForm(reqBody,form)).getElementById(speci).innerHTML
    });
}