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

document.addEventListener('DOMContentLoaded', function initEventListener() {
    ['.ea-new-form', '.ea-edit-form'].forEach((formSelector) => {
        const form = document.querySelector(formSelector);
        if (null === form) return; //formSelector.includes('-new-') ? 'new' : 'edit')
        changeOptions(form,'Notice_champDisc','Notice_discipline','Notice_specialite')

        if(null !== document.getElementById('Notice_disciFond'))
            changeOptions(form,'Notice_disciFond','Notice_division','Notice_codewey')
    })
});