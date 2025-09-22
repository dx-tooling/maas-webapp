import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["container", "template", "saveButton"];
    static values = {
        initialData: Object,
    };

    connect() {
        this.initialState = this.captureCurrentState();
        this.updateSaveButtonState();
    }

    add() {
        const template = this.templateTarget.content.cloneNode(true);
        this.containerTarget.appendChild(template);

        // Add event listeners to the new row
        this.addEventListenersToRow(template.querySelector(".env-var-row"));
        this.updateSaveButtonState();
    }

    remove(event) {
        event.target.closest(".env-var-row").remove();
        this.updateSaveButtonState();
    }

    inputChanged() {
        this.updateSaveButtonState();
    }

    captureCurrentState() {
        const rows = this.containerTarget.querySelectorAll(".env-var-row");
        const state = [];

        rows.forEach((row) => {
            const keyInput = row.querySelector('input[name="env_keys[]"]');
            const valueInput = row.querySelector('input[name="env_values[]"]');

            if (keyInput && valueInput) {
                state.push({
                    key: keyInput.value.trim(),
                    value: valueInput.value.trim(),
                });
            }
        });

        return state;
    }

    hasChanges() {
        const currentState = this.captureCurrentState();

        // Compare with initial state
        if (currentState.length !== this.initialState.length) {
            return true;
        }

        // Check if any key-value pairs have changed
        for (let i = 0; i < currentState.length; i++) {
            const current = currentState[i];
            const initial = this.initialState[i];

            if (!initial || current.key !== initial.key || current.value !== initial.value) {
                return true;
            }
        }

        return false;
    }

    updateSaveButtonState() {
        const hasChanges = this.hasChanges();
        const saveButton = this.saveButtonTarget;

        if (hasChanges) {
            saveButton.disabled = false;
            saveButton.classList.remove("etfswui-button-default-disabled");
            saveButton.classList.add("etfswui-button-default");
        } else {
            saveButton.disabled = true;
            saveButton.classList.remove("etfswui-button-default");
            saveButton.classList.add("etfswui-button-default-disabled");
        }
    }

    addEventListenersToRow(row) {
        const keyInput = row.querySelector('input[name="env_keys[]"]');
        const valueInput = row.querySelector('input[name="env_values[]"]');

        if (keyInput) {
            keyInput.addEventListener("input", () => this.inputChanged());
        }

        if (valueInput) {
            valueInput.addEventListener("input", () => this.inputChanged());
        }
    }
}
