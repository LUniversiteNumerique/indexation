import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String
    }
    initialize() {
        this._onPreConnect = this._onPreConnect.bind(this);
        this._onConnect = this._onConnect.bind(this);
    }

    connect() {
        this.element.addEventListener('autocomplete:pre-connect', this._onPreConnect);
        this.element.addEventListener('autocomplete:connect', this._onConnect);
    }

    disconnect() {
        this.element.removeEventListener('autocomplete:connect', this._onConnect);
        this.element.removeEventListener('autocomplete:pre-connect', this._onPreConnect);
    }

    _onPreConnect(event) {
        const url = this.urlValue;
        event.detail.options.create = function (input, callback) {
            fetch(url, {method: 'POST', body: {nom: input}})
                .then(response => response.json())
                .then(data => callback({value: data.id, text: data.name}));
        }
    }

    _onConnect(event) {
        // TomSelect has just been initialized and you can access details from the event
        console.log(event.detail.options); // Options used to initialize TomSelect
    }
}
