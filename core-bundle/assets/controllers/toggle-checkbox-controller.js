import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.start = null;
        this.checkboxes = this.element.querySelectorAll('input[type=checkbox]');

        this.checkboxes.forEach(cbx => {
            cbx.addEventListener('click', (e) => {
                if (e.shiftKey && this.start) {
                    this.shiftToggle(e.currentTarget);
                }

                this.start = e.currentTarget;
            })
        })

        this.element.querySelectorAll('.toggle_select')?.forEach(row => {
            // Do not propagate the form field click events
            this.preventRowClickEvents(row);

            row.addEventListener('click', e => this.clickEvent(e));
        });
    }

    shiftToggle(cbx) {
        const currIndex = [...this.checkboxes].indexOf(cbx);
        const startIndex = [...this.checkboxes].indexOf(this.start);

        const from = Math.min(currIndex, startIndex);
        const to = Math.max(currIndex, startIndex);

        const status = this.checkboxes[startIndex].checked;

        for (let i = from; i <= to; i++) {
            this.checkboxes[i].checked = status;
        }
    }

    preventRowClickEvents(e) {
        e.currentTarget?.querySelectorAll('label, input[type=checkbox], input[type=radio]')?.forEach(
            i => i.addEventListener('click', e => e.stopPropagation())
        );
    }

    clickEvent(e) {
        const input = this.element.querySelector('input[type=checkbox], input[type=radio]');

        if (
            !input
            || input.disabled
            || e.currentTarget.closest('.limit_toggler')
        ) {
            return;
        }

        if ('radio' === input.type) {
            input.checked = true;
            return;
        }

        if (e.shiftKey && this.start) {
            this.shiftToggle(input);
        } else {
            input.checked = !input.checked;

            // Check for Backend.toggleCheckboxes integration
            if (input.getAttribute('onclick') === 'Backend.toggleCheckboxes(this)') {
                Backend.toggleCheckboxes(input); // see #6399
            }
        }

        this.start = input;
    }
}
