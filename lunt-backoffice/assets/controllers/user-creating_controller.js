import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["form", "masterSelect"];

    initialize() {
        this.schoolParent = document.getElementById('User_school').parentElement.parentElement;
        this.unthemeParent = document.getElementById('User_untheme').parentElement.parentElement;
        this.toparent = this.schoolParent.parentElement
    }

    connect() {
        // Add event listener when the controller connects
        this.schoolParent.remove();
        this.unthemeParent.remove();
        this.masterSelectTarget.addEventListener("change", this.changeOptionsRemove.bind(this));
    }

    disconnect() {
        // Cleanup event listener when the controller disconnects
        this.masterSelectTarget.removeEventListener("change", this.changeOptionsRemove.bind(this));
    }

    async changeOptionsRemove({target}) {
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
