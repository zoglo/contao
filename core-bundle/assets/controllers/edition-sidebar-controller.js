import { Controller } from '@hotwired/stimulus';

const preference = 'contao--edition-sidebar-dismissed';

export default class extends Controller {
    connect() {
        this.element.hidden = localStorage.getItem(preference) === 'true';
    }

    dismiss() {
        localStorage.setItem(preference, 'true');
        const offer = this.element.querySelector('.edition-offer');
        this.application.getControllerForElementAndIdentifier(offer, 'contao--toggle-receiver')?.close();
        this.element.hidden = true;
        document.getElementById('cms-badge-button')?.focus();
    }
}
