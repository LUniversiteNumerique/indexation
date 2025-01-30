import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["form", "ressToggle"];

    initialize() {
        this.zipParent = document.querySelector('#Notice_ressZip_file').closest(".form-group");
        this.urlParent = document.querySelector('#Notice_ressUrl').closest(".form-group");
        this.toparent = this.urlParent.closest(".row")
    }

    connect() {
        this.zipParent.remove(); // Add event listener when the controller connects
        this.ressToggleTarget.addEventListener("change", this.toggleZipButton.bind(this));
    }

    disconnect() {
        this.ressToggleTarget.removeEventListener("change", this.toggleZipButton.bind(this));
    }

    async toggleZipButton({target}) {
        const haStyleZip = this.toparent.contains(this.zipParent)
        const haStyleUrl = this.toparent.contains(this.urlParent)

        if (target.checked) {
            if (!haStyleZip) this.toparent.appendChild(this.zipParent);
            if (haStyleUrl) this.urlParent.remove();
        } else {
            if (!haStyleUrl) this.toparent.appendChild(this.urlParent);
            if (haStyleZip) this.zipParent.remove();
        }
    }
}
