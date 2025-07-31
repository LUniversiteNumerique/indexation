document.addEventListener('DOMContentLoaded', () => {
  ajoutDeweyPerso();
  ['.ea-new-form', '.ea-edit-form'].forEach((formSelector) => {
    const form = document.querySelector(formSelector);
    if (form === null) return;


    handleFieldsWithErrors(form, formSelector.includes('-new-') ? 'new' : 'edit');

    const setupDynamicGroups = () => {
      // Boucle sur chaque Groupe
      form.querySelectorAll('[id$="_champDisc"]').forEach(champDisc => {
        // Récupération de l'id du sélecteur chargé
        const index = champDisc.id.match(/\d+/)?.[0];
        if (!index) {
          return;
        }
        // Récupération des champs
        const discipline = document.getElementById(`Notice_disciplineGroups_${index}_discipline`);
        const specialites = document.getElementById(`Notice_disciplineGroups_${index}_specialites`);

        if (!discipline || !specialites) {
          return;
        }
        changeOptions(form, champDisc.id, discipline.id, specialites.id);
      });
    };
    const setupDynamicDeweyGroups = () => {
      // Boucle sur chaque Groupe
      form.querySelectorAll('[id$="_dewey"]').forEach(dewey => {
        // Récupération de l'id du sélecteur chargé
        const index = dewey.id.match(/\d+/)?.[0];
        if (!index) {
          return;
        }
        // Récupération des champs
        const division = document.getElementById(`Notice_deweyGroups_${index}_division`);
        const codeDewey = document.getElementById(`Notice_deweyGroups_${index}_codeweys`);

        if (!division || !codeDewey) {
          return;
        }
        changeOptionsDewey(form, dewey.id, division.id, codeDewey.id);
      });
    };

    // MutationObserver pour détecter quand un nouveau Groupe apparaît
    const observer = new MutationObserver(() => {
      // groupe spécialités
      const allGroups = document.querySelectorAll('[id^="Notice_disciplineGroups_"][id$="_champDisc"]');
      const dernierChamp = allGroups[allGroups.length - 1];

      // Empêche d'initialiser un champ init
      if (dernierChamp && !dernierChamp.classList.contains('initialized')) {
        const match = dernierChamp.id.match(/\d+/);
        if (match) {
          const index = match[0];
          initializeNewSelectGroup(index);
        }
      }

      // groupe dewey
      const DeweyGroups = document.querySelectorAll('[id^="Notice_deweyGroups_"][id$="_dewey"]');
      const deweyChamp = DeweyGroups[DeweyGroups.length - 1];

      // Empêche d'initialiser un champ init
      if (deweyChamp && !deweyChamp.classList.contains('initialized')) {
        const match = deweyChamp.id.match(/\d+/);
        if (match) {
          const index = match[0];
          initializeNewDeweyGroup(index);
        }
      }

      setupDynamicGroups();
      setupDynamicDeweyGroups();
    });

    observer.observe(form, {
      childList: true,
      subtree: true,
    });
  });
});

// Fonction pour initialiser un groupe de sélection dynamique
const initializeNewSelectGroup = (index) => {
  const form = document.querySelector('.ea-new-form') || document.querySelector('.ea-edit-form');

  const champId = `Notice_disciplineGroups_${index}_champDisc`;
  const disciId = `Notice_disciplineGroups_${index}_discipline`;
  const speciId = `Notice_disciplineGroups_${index}_specialites`;

  setTimeout(() => {
    const champ = document.getElementById(champId);
    const disci = document.getElementById(disciId);
    const speci = document.getElementById(speciId);

    if (champ && disci && speci && !champ.classList.contains('initialized')) {
      // Empêcher la réinitialisation multiple
      champ.classList.add('initialized');
      // Désactive les sélecteurs discipline et spécialités
      if(disci.options.length === 0){
        disci.add(new Option('Sélectionner la disicipline', ''));
      }
      if(speci.options.length === 0){
        speci.add(new Option('', ''));
      }

      changeOptions(form, champId, disciId, speciId);
    }
  }, 100);
};


// Fonction pour initialiser un groupe de sélection dynamique
const initializeNewDeweyGroup = (index) => {
  const form = document.querySelector('.ea-new-form') || document.querySelector('.ea-edit-form');

  const deweyId = `Notice_deweyGroups_${index}_dewey`;
  const divisionId = `Notice_deweyGroups_${index}_division`;
  const codedeweyId = `Notice_deweyGroups_${index}_codeweys`;

  setTimeout(() => {
    const dewey = document.getElementById(deweyId);
    const division = document.getElementById(divisionId);
    const codeDewey = document.getElementById(codedeweyId);

    if (dewey && division && codeDewey && !dewey.classList.contains('initialized')) {
      // Empêcher la réinitialisation multiple
      dewey.classList.add('initialized');
      // Désactive les sélecteurs division et codedewey
      if (division.options.length === 0) {
        division.add(new Option('Sélectionner un champ division', ''));
      }

      if (codeDewey.options.length === 0) {
        codeDewey.add(new Option('', ''));
      }

      changeOptionsDewey(form, deweyId, divisionId, codedeweyId);
    }
  }, 100);
};
const changeOptions = (form, champId, disciId, speciId) => {
  const form_select_champ = document.getElementById(champId);
  const form_select_disci = document.getElementById(disciId);
  const form_select_speci = document.getElementById(speciId);

  if (!form_select_champ.dataset.listenerAttached) {
    form_select_champ.addEventListener('change', async ({ target }) => {
      // Réinitialise discipline et spécialités à chaque changement de champDisc
      form_select_disci.innerHTML = '';
      form_select_disci.add(new Option('Sélectionner la disicipline', ''));
      form_select_speci.innerHTML = '';
      form_select_speci.add(new Option('', ''));
      // Puis met à jour discipline et spécialités si besoin
      const resText = await updateForm(`${target.getAttribute('name')}=${target.value}`, form);
      form_select_disci.innerHTML = resText.getElementById(disciId).innerHTML;
      form_select_speci.innerHTML = resText.getElementById(speciId).innerHTML;
    });
    form_select_champ.dataset.listenerAttached = "true";
  }

  if (!form_select_disci.dataset.listenerAttached) {
    form_select_disci.addEventListener('change', async ({ target }) => {
      const reqBody = `${form_select_champ.getAttribute('name')}=${form_select_champ.value}&${target.getAttribute('name')}=${target.value}`;
      form_select_speci.innerHTML = (await updateForm(reqBody, form)).getElementById(speciId).innerHTML;
    });
    form_select_disci.dataset.listenerAttached = "true";
  }
};
const changeOptionsDewey = (form, deweyId, diviId, codeDeId) => {
  const form_select_dewey = document.getElementById(deweyId);
  const form_select_divi = document.getElementById(diviId);
  const form_select_codeDe = document.getElementById(codeDeId);

  // Empêche les doublons
  if (!form_select_dewey.dataset.listenerAttached) {
    form_select_dewey.addEventListener('change', async ({ target }) => {
      form_select_divi.innerHTML = '';
      form_select_divi.add(new Option('Sélectionner une division', ''));
      form_select_codeDe.innerHTML = '';
      form_select_codeDe.add(new Option('', ''));
      const resText = await updateForm(`${target.getAttribute('name')}=${target.value}`, form);
      form_select_divi.innerHTML = resText.getElementById(diviId).innerHTML;
      form_select_codeDe.innerHTML = resText.getElementById(codeDeId).innerHTML;
    });
    form_select_dewey.dataset.listenerAttached = "true";
  }

  if (!form_select_divi.dataset.listenerAttached) {
    form_select_divi.addEventListener('change', async ({ target }) => {
      const reqBody = `${form_select_dewey.getAttribute('name')}=${form_select_dewey.value}&${target.getAttribute('name')}=${target.value}`;
      form_select_codeDe.innerHTML = (await updateForm(reqBody, form)).getElementById(codeDeId).innerHTML;
    });
    form_select_divi.dataset.listenerAttached = "true";
  }
};

const filedErrors = (input, inputEvent) => {
  const formTab = input.closest('div.tab-pane');
  if (formTab) {
    const navLinkTab = document.querySelector(`[data-bs-target="#${ formTab.id }"], a[href="#${ formTab.id }"]`);
    if (navLinkTab) {
      navLinkTab.classList.add('has-error');

      const badge = navLinkTab.querySelector('.badge');
      if (badge) {
        badge.textContent = (parseInt(badge.textContent) + 1).toString();
      } else {
        let newErrorBadge = document.createElement('span');
        newErrorBadge.classList.add('badge', 'badge-danger');
        newErrorBadge.textContent = '1';
        navLinkTab.appendChild(newErrorBadge);
      }
    }
  }

  const formGroup = input.closest('div.form-group');
  formGroup.classList.add('has-error');

  input.addEventListener(inputEvent, function onFormGroupClick() {
    formGroup.classList.remove('has-error');
    formGroup.removeEventListener('click', onFormGroupClick);
  });
};

const handleFieldsWithErrors = (form, pageName) => {
  const that = this;
  document.querySelector('.ea-edit, .ea-new').querySelectorAll('[type="submit"]').forEach((button) => {
    button.addEventListener('click', function onSubmitButtonsClick(clickEvent) {
      let formHasErrors = false;
      document.querySelectorAll('.form-tabs-tablist .nav-item .badge-danger.badge').forEach((badge) => {
        badge.parentElement.removeChild(badge);
      });

      if (null !== form.getAttribute('novalidate')) {
        return;
      }

      form.querySelectorAll('select, input, textarea').forEach((input) => {
        if (!input.disabled && !input.validity.valid) {
          formHasErrors = true;
          filedErrors(input, 'change');
        }
      });
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
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'charset': 'utf-8' }
  });

  return (new DOMParser()).parseFromString(await req.text(), 'text/html');
};

function ajoutDeweyPerso() {
  const btn = document.getElementById("add_dewey_perso_btn");
  if (!btn) return;

  btn.onclick = function() {
    const code = document.getElementById("dewey_perso_code").value;
    const nom = document.getElementById("dewey_perso_nom").value;
    fetch("/admin/dewey-perso/add", {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify({code, nom})
    })
      .then(r => r.json())
      .then(data => {
        const msg = document.getElementById("dewey_perso_add_msg");
        if (data.success) {
          msg.textContent = "Ajouté !";
          msg.style.color = "green";
          document.getElementById("dewey_perso_code").value = "";
          document.getElementById("dewey_perso_nom").value = "";

          const select = document.querySelector("select[name$='[deweyPersos][]']");
          if (select) {
            let exists = false;
            for (let i = 0; i < select.options.length; i++) {
              if (select.options[i].value == data.id) {
                exists = true;
                select.options[i].selected = true;
                break;
              }
            }
            if (!exists) {
              const opt = document.createElement("option");
              opt.value = data.id;
              opt.text = code + ' - ' + data.nom;
              opt.selected = true;
              select.appendChild(opt);
            }
            if (window.jQuery && $(select).data("select2")) {
              $(select).trigger("change");
            }
          }
        } else {
          msg.textContent = data.error || "Erreur";
          msg.style.color = "red";
        }
      })
      .catch(() => {
        const msg = document.getElementById("dewey_perso_add_msg");
        msg.textContent = "Erreur technique ou accès refusé";
        msg.style.color = "red";
      });
  };
}
