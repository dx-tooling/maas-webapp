import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["container", "template"];

    add() {
        const template = this.templateTarget.content.cloneNode(true);
        this.containerTarget.appendChild(template);
    }

    remove(event) {
        event.target.closest(".env-var-row").remove();
    }
}
