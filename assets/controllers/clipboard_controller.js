import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'button'];
    static values = { done: String };

    async copy() {
        this.sourceTarget.select();
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
            this.buttonTarget.textContent = this.doneValue;
        } catch {
            document.execCommand('copy');
        }
    }
}
