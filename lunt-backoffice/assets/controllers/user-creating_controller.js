import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["form", "masterSelect"];

    initialize() {
        this.schoolParent = document.querySelector('#User_school').closest(".form-group");
        this.unthemeParent = document.querySelector('#User_untheme').closest(".form-group");
        this.toparent = this.schoolParent.parentElement
    }

    connect() {
        this.schoolParent.remove(); this.unthemeParent.remove();
        this.masterSelectTarget.addEventListener("change", this.selectOptionsChange.bind(this));
    }

    disconnect() {
        this.masterSelectTarget.removeEventListener("change", this.selectOptionsChange.bind(this));
    }

    async selectOptionsChange({target}) {
        const haStyleSchool = this.toparent.contains(this.schoolParent)
        const haStyleUntheme = this.toparent.contains(this.unthemeParent)

        if (target.value === '3') {
            if (!haStyleUntheme) this.toparent.appendChild(this.unthemeParent);
            if (haStyleSchool) this.schoolParent.remove();
        } else if (target.value === '2') {
            if (!haStyleSchool) this.toparent.appendChild(this.schoolParent);
            if (haStyleUntheme) this.unthemeParent.remove();
        } else {
            if (haStyleSchool) this.schoolParent.remove();
            if (haStyleUntheme) this.unthemeParent.remove();
        }
    }
}
