import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String
    }
    initialize() {
        this._onPreConnect = this._onPreConnect.bind(this);
    }

    connect() {
        this.element.addEventListener('autocomplete:pre-connect', this._onPreConnect);
    }

    disconnect() {
        this.element.removeEventListener('autocomplete:pre-connect', this._onPreConnect);
    }

    _onPreConnect(event) {
        const options = event.detail.options;
        options.render = {
            no_results: () =>'<div class="no-results">Aucun résultat trouvé</div>',
            loading_more: () => `<div class="loading-more-results">Chargement d'autres résultats</div>`,
            no_more_results: () => `<div class="no-more-results">Aucun résultat trouvé</div>`,
            option_create: (data, escape) => `<div class="create">Ajouter <strong>${escape(data.input)}</strong>&hellip;</div>`,
        };
        const url = this.urlValue;
        options.create = function (input, callback) {
            fetch(url, {method: 'POST', body: JSON.stringify({nom: input})})
                .then(r => r.json())
                .then(data => callback(data));
        }
    }
}
