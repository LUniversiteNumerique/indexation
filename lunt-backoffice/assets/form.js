const updateForm = async (data, form) => {
    const action = '?crudAction=new&crudControllerFqcn=App%5CController%5CNoticeCrudController';
    const req = await fetch(action, {
        method: form.getAttribute('method'), body: data,
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'charset': 'utf-8'}
    });

    return (new DOMParser()).parseFromString(await req.text(), 'text/html');
};

const changeOptions = (form, champ,disci,speci) => {
    let reqBody = null
    const form_select_champ = document.getElementById(champ);
    const form_select_disci = document.getElementById(disci);
    const form_select_speci = document.getElementById(speci);

    form_select_champ.addEventListener('change', async e => {
        reqBody = e.target.getAttribute('name') +'='+ e.target.value;
        form_select_disci.innerHTML = (await updateForm(reqBody,form)).getElementById(disci).innerHTML
    });
    form_select_disci.addEventListener('change', async e => {
        if(reqBody===null) reqBody = form_select_champ.getAttribute('name') +'='+ form_select_champ.value
        reqBody += '&'+ e.target.getAttribute('name') +'='+ e.target.value;
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