import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        id: String,
        field: String,
    };

    #loading = false;

    async toggle() {
        const subpalette = document.getElementById(this.idValue);

        if (!subpalette) {
            await this.#load();
            return;
        }

        const checked = this.element.checked;

        subpalette.style.display = checked ? '' : 'none';

        // Hidden fields must not block the form submission
        for (const el of subpalette.querySelectorAll(checked ? '[data-required]' : '[required]')) {
            el.toggleAttribute('required', checked);
            el.toggleAttribute('data-required', !checked);
        }

        const response = await this.#post({ state: checked ? 1 : 0 });

        if (response?.ok) {
            const template = document.createElement('template');
            template.innerHTML = await response.text();

            this.#updateVersionNumber(template.content);
        }
    }

    async #load() {
        if (this.#loading) {
            return;
        }

        this.#loading = true;

        const response = await this.#post({ load: 1, state: 1 });

        this.#loading = false;

        if (!response?.ok) {
            this.element.checked = false;
            return;
        }

        const subpalette = document.createElement('div');
        subpalette.id = this.idValue;
        subpalette.className = 'subpal widget-group';

        // Unlike innerHTML, a contextual fragment executes the inline scripts of the widgets
        subpalette.append(document.createRange().createContextualFragment(await response.text()));

        this.element.closest('.widget').after(subpalette);
        this.element.checked = true;

        this.#updateVersionNumber(subpalette);

        // HOOK
        document.dispatchEvent(new CustomEvent('ajax_change', { bubbles: true }));

        // Backwards compatibility with MooTools listeners
        window.fireEvent?.('ajax_change');
    }

    async #post(params) {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({
                action: 'toggleSubpalette',
                id: this.idValue,
                field: this.fieldValue,
                ...params,
            }),
        });

        const url = response.headers.get('X-Ajax-Location');

        if (url) {
            window.location.replace(url);
            return null;
        }

        return response;
    }

    #updateVersionNumber(container) {
        const version = container.querySelector('input[name="VERSION_NUMBER"]')?.value;

        if (undefined === version) {
            return;
        }

        for (const input of this.element.form.querySelectorAll('input[name="VERSION_NUMBER"]')) {
            input.value = version;
        }
    }
}
